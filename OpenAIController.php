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

        // Note: We're not running the assistant here anymore, as it will be done asynchronously
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
        return response($status); // Assuming the status is a JSON string
    }
    public function getMessages()
{
    $threadId = Session::get('threadId');
    
    if (!$threadId) {
        return response()->json(['error' => 'Thread ID not found in session.'], 400);
    }
    
    $messagesJson = $this->runPHPScript('getMessages', [$threadId]);
    $messagesData = json_decode($messagesJson, true);

    foreach ($messagesData as $key => $message) {
        // Fetch the list of files for each message
        $fileIdsJson = $this->runPHPScript('listMessageFiles', [$threadId, $message['id']]);
        $fileIds = json_decode($fileIdsJson, true);

        // Check if there are any files associated with the message
        if (!empty($fileIds)) {
            $fileId = $fileIds[0]; // Assuming each message has at most one file
            $messagesData[$key]['fileId'] = $fileId;

            // Store threadId and messageId for each fileId
            Session::put($fileId . '-threadId', $threadId);
            Session::put($fileId . '-messageId', $message['id']);
        } else {
            $messagesData[$key]['fileId'] = null;
        }
    }
    
    return response()->json($messagesData);
}


public function downloadMessageFile($fileId)
{
    // Call the PHP script to download the file content
    $fileContent = $this->runPHPScript('retrieveMessageFile', [$fileId]);

    // Determine the content type and filename (you may need to adjust this based on your requirements)
    $headers = [
        'Content-Type' => 'application/octet-stream', // Adjust this according to the actual file type
        'Content-Disposition' => 'attachment; filename="' . $fileId . '.csv"' // Example filename, adjust as needed
    ];

    return response($fileContent)->withHeaders($headers);
}




    


    public function retrieveMessageFile($threadId, $messageId, $fileId)
    {
        return $this->runPHPScript('retrieveMessageFile', [$threadId, $messageId, $fileId]);
    }

    public function listMessageFiles($threadId, $messageId)
    {
        return $this->runPHPScript('listMessageFiles', [$threadId, $messageId]);
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

    private function runPHPScript($function, $args = [])
{
    $scriptPath = '/Users/dysisx/Documents/assistant/app/Http/Controllers/OpenaiAssistantController.php';

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