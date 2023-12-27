<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class OpenAIController extends Controller
{
    public function index()
    {
        return view('assistant');
    }

    public function submitMessage(Request $request)
    {
        $userMessage = $request->input('message');
        $assistantId = Session::get('assistantId');
        $threadId = Session::get('threadId');

        if (!$assistantId || !$threadId) {
            throw new \Exception('Assistant or thread ID not found in session.');
        }

        $this->runPHPScript('addMessage', [$threadId, 'user', $userMessage]);

        return view('assistant', ['threadId' => $threadId, 'assistantId' => $assistantId]);
    }

    public function startRun(Request $request)
    {
        $threadId = Session::get('threadId');
        $assistantId = Session::get('assistantId');

        if (!$assistantId || !$threadId) {
            return response()->json(['error' => 'Assistant or thread ID not found in session.'], 400);
        }

        $runId = $this->runPHPScript('runAssistant', [$threadId, $assistantId]);
        return response()->json(['runId' => $runId]);
    }

    public function checkRunStatus(Request $request)
    {
        $runId = $request->input('runId');
        $threadId = Session::get('threadId');

        if (!$threadId) {
            return response()->json(['error' => 'Thread ID not found in session.'], 400);
        }

        $status = $this->runPHPScript('checkRunStatus', [$threadId, $runId]);
        return response($status);
    }

    public function getMessages()
    {
        $threadId = Session::get('threadId');

        if (!$threadId) {
            return response()->json(['error' => 'Thread ID not found in session.'], 400);
        }

        $messagesJson = $this->runPHPScript('getMessages', [$threadId]);
        $messages = json_decode($messagesJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Error parsing message data.'], 500);
        }

        return response()->json($messages);
    }

    public function downloadFile($fileId) {
        $apiKey = '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'; // Secure this appropriately
        $fileContent = $this->fetchFileFromOpenAI($fileId, $apiKey);

        if (!$fileContent) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Assuming the file is a CSV for simplicity
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="downloaded_file.csv"',
        ];

        return response($fileContent, 200, $headers);
    }

    private function fetchFileFromOpenAI($fileId, $apiKey) {
        $url = "https://api.openai.com/v1/files/" . $fileId . "/content";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function saveFilesFromThread($threadId) {
        // Define the path to the public directory where files will be saved
        $outputDir = public_path('robots.txt');

        // Call the external PHP script to save files to the specified directory
        return $this->runPHPScript('saveFilesFromThread', [$threadId, $outputDir]);
    }

    public function deleteThread()
    {
        $this->runPHPScript('deleteThread', [Session::get('threadId')]);
        Session::forget('threadId');
        return redirect('/');
    }

    public function deleteAssistant()
    {
        $this->runPHPScript('deleteAssistant', [Session::get('assistantId')]);
        Session::forget('assistantId');
        return redirect('/');
    }

    public function createNewThread()
    {
        $threadId = $this->runPHPScript('createThread');
        Session::put('threadId', $threadId);
        return redirect('/');
    }

    public function createNewAssistant()
    {
        $assistantId = $this->runPHPScript('createAssistant');
        Session::put('assistantId', $assistantId);
        return redirect('/');
    }

    private function runPHPScript($function, $args = []) {
        $scriptPath = '/Users/dysisx/Documents/assistant/app/Http/Controllers/OpenaiAssistantController.php'; // Correctly updated path
    
        $process = new Process(array_merge(['php', $scriptPath, $function], $args));
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
