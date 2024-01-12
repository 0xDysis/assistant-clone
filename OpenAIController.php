<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\Order;
use App\Services\MessageService;
use App\Services\RunService;
use App\Services\FileDownloadService;
use App\Services\DatabaseExportService;
use App\Services\SessionValidationService;
use App\Services\ThreadService;
use App\Services\AssistantService;

class OpenAIController extends Controller
{
    public function index()
{
    if (!Cache::has('processedMessages')) {
        Cache::put('processedMessages', [], 3600);
    }

    return view('assistant');
}
    protected $messageService;
    protected $runService;
    protected $fileDownloadService;
    protected $databaseExportService;
    protected $sessionValidationService;
    protected $threadService;
    protected $assistantService;

    public function __construct(
        MessageService $messageService, 
        RunService $runService, 
        FileDownloadService $fileDownloadService, 
        DatabaseExportService $databaseExportService,
        SessionValidationService $sessionValidationService,
        ThreadService $threadService,
        AssistantService $assistantService,
    ) {
        $this->messageService = $messageService;
        $this->runService = $runService;
        $this->fileDownloadService = $fileDownloadService;
        $this->databaseExportService = $databaseExportService;
        $this->sessionValidationService = $sessionValidationService;
        $this->threadService = $threadService;
        $this->assistantService = $assistantService;
    }
    public function submitMessage(Request $request)
    {
        $this->sessionValidationService->validate(['assistantId', 'threadId']);
        $userMessage = $request->input('message');
        $threadId = Session::get('threadId'); 
        $assistantId = Session::get('assistantId');
    
        $this->messageService->submitMessage($threadId, $assistantId, $userMessage);
    
        return view('assistant', [
            'threadId' => $threadId, 
            'assistantId' => $assistantId
        ]);
    }
    
    public function startRun(Request $request)
    {
        $this->sessionValidationService->validate(['assistantId', 'threadId']);
        $threadId = Session::get('threadId');
        $assistantId = Session::get('assistantId');
    
        $runId = $this->runService->startRun($threadId, $assistantId);
        
        return response()->json(['runId' => $runId]);
    }
    
    public function checkRunStatus(Request $request)
    {
        $this->sessionValidationService->validate(['threadId']);
        $threadId = Session::get('threadId');
        $runId = $request->input('runId');
    
        $status = $this->runService->checkRunStatus($threadId, $runId);
    
        return response($status);
    }
    
    public function getMessages()
    {
        $this->sessionValidationService->validate(['threadId']);
        $threadId = Session::get('threadId');
        
        $messagesData = $this->messageService->getMessages($threadId);
    
        return response()->json($messagesData);
    }
    
    public function downloadMessageFile($fileId)
    {
        return $this->fileDownloadService->downloadMessageFile($fileId);
    }
    
    public function createNewAssistantWithCsv()
    {
        return $this->databaseExportService->createNewAssistantWithCsv();
    }
    
    public function deleteThread()
    {
        $threadId = Session::get('threadId');
        $this->threadService->deleteThread($threadId);
        return redirect('/');
    }

    public function deleteAssistant()
    {
        $assistantId = Session::get('assistantId');
        $this->assistantService->deleteAssistant($assistantId);
        return redirect('/');
    }

    public function createNewThread()
    {
        $this->threadService->createNewThread();
        return redirect('/');
    }
    
}
