<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\Order;

class OpenAIController extends Controller
{
    private $phpBinaryPath = '/Users/dysisx/Library/Application Support/Herd/bin/php';
    private $scriptPath = '/Users/dysisx/Documents/assistant/app/Http/Controllers/OpenaiAssistantController.php';

    public function index()
    {
        if (!Cache::has('processedMessages')) {
            Cache::put('processedMessages', [], 3600);
        }

        return view('assistant');
    }

    public function submitMessage(Request $request)
    {
        $this->validateSession(['assistantId', 'threadId']);
        $userMessage = $request->input('message');
        $this->runPHPScript('addMessage', [Session::get('threadId'), 'user', $userMessage]);

        return view('assistant', [
            'threadId' => Session::get('threadId'), 
            'assistantId' => Session::get('assistantId')
        ]);
    }

    public function startRun(Request $request)
    {
        $this->validateSession(['assistantId', 'threadId']);
        $threadId = Session::get('threadId');
        $assistantId = Session::get('assistantId');
        $runId = $this->runPHPScript('runAssistant', [$threadId, $assistantId]);
    
        return response()->json(['runId' => $runId]);
    }

    public function checkRunStatus(Request $request)
    {
        $this->validateSession(['threadId']);
        $threadId = Session::get('threadId');
        $runId = $request->input('runId');
        $status = $this->runPHPScript('checkRunStatus', [$threadId, $runId]);

        return response($status);
    }

    public function getMessages()
    {
        $this->validateSession(['threadId']);
        $threadId = Session::get('threadId');
        $messagesData = $this->fetchAndProcessMessages($threadId);

        return response()->json($messagesData);
    }

    private function fetchAndProcessMessages($threadId) 
    {
        $messagesJson = $this->runPHPScript('getMessages', [$threadId]);
        $messagesData = json_decode($messagesJson, true);
        $processedMessages = Cache::get('processedMessages', []);

        foreach ($messagesData as $key => $message) {
            if (isset($processedMessages[$message['id']])) {
                continue; 
            }

            $messagesData[$key]['fileId'] = $this->processMessageForFileId($threadId, $message);
            $processedMessages[$message['id']] = 1;
        }

        Cache::put('processedMessages', $processedMessages, 3600);
        return $messagesData;
    }

    private function processMessageForFileId($threadId, $message)
    {
        $fileIdsJson = $this->runPHPScript('listMessageFiles', [$threadId, $message['id']]);
        $fileIds = json_decode($fileIdsJson, true);

        if (!empty($fileIds) && is_array($fileIds)) {
            $fileId = $fileIds[0];
            $this->storeFileMetadataInSession($fileId, $threadId, $message['id'], $message['content']);
            return $fileId;
        }

        return null;
    }

    private function storeFileMetadataInSession($fileId, $threadId, $messageId, $content)
    {
        $fileName = $this->extractFileNameFromContent($content);
        Session::put($fileId . '-threadId', $threadId);
        Session::put($fileId . '-messageId', $messageId);
        Session::put($fileId . '-fileName', $fileName);
    }

    private function extractFileNameFromContent($content)
    {
        if (preg_match('/\[Download (.*?)\]\(sandbox:/', $content, $matches)) {
            return $matches[1];
        }

        return 'defaultFileName.txt';
    }

    public function downloadMessageFile($fileId)
    {
        $fileContent = $this->retrieveMessageFile($fileId);
        if (!$fileContent) {
            return $this->createErrorResponse('File not found or unable to retrieve.', 404);
        }

        $fileName = Session::get($fileId . '-fileName', 'defaultFile.csv');
        $contentType = $this->getContentTypeByExtension(pathinfo($fileName, PATHINFO_EXTENSION));

        return $this->createFileDownloadResponse($fileContent, $fileName, $contentType);
    }

    private function createFileDownloadResponse($fileContent, $fileName, $contentType)
    {
        return response()->streamDownload(function () use ($fileContent) {
            echo $fileContent;
        }, $fileName, ['Content-Type' => $contentType]);
    }

    private function getContentTypeByExtension($extension)
    {
        $mimeTypes = [
            // Mime types mapping
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream'; 
    }

    public function retrieveMessageFile($fileId)
    {
        return $this->runPHPScript('retrieveMessageFile', [$fileId]);
    }

    public function deleteThread()
    {
        $this->runPHPScript('deleteThread', [Session::get('threadId')]);
        Session::forget('threadId');
        Cache::forget('processedMessages');
    
        return redirect('/');
    }

    public function deleteAssistant()
    {
        $this->runPHPScript('deleteAssistant', [Session::get('assistantId')]);
        Session::forget('assistantId');
        Cache::forget('processedMessages');
        return redirect('/');
    }

    public function createNewThread()
    {
        $threadId = $this->runPHPScript('createThread');
        Session::put('threadId', $threadId);
        Cache::forget('processedMessages');

        return redirect('/');
    }

    public function createNewAssistantWithCsv()
    {
        $csvData = $this->convertOrdersToCsv();
        $tempFilePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFilePath, $csvData);
        $assistantId = $this->runPHPScript('createAssistant', [$tempFilePath]);
    
        Session::put('assistantId', $assistantId);
        unlink($tempFilePath);
    
        return redirect('/');
    }

    private function convertOrdersToCsv()
    {
        $orders = Order::all();
        $csvData = "order_id,customer_name,order_total\n";
        foreach ($orders as $order) {
            $csvData .= "{$order->order_id},{$order->customer_name},{$order->order_total}\n";
        }

        return $csvData;
    }

    private function validateSession(array $keys)
    {
        foreach ($keys as $key) {
            if (!Session::has($key)) {
                throw new \Exception("$key not found in session.");
            }
        }
    }

    private function runPHPScript($function, $args = [])
    {
        $process = new Process(array_merge([$this->phpBinaryPath, $this->scriptPath, $function], $args));
        $process->setWorkingDirectory(base_path());  
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());
        if (!$output) {
            throw new \Exception("No output from PHP script for function: $function");
        }

        return $output;
    }
}
