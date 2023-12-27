<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

$apiKey = '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';
$client = OpenAI::client($apiKey);

function createAssistant($client) {
    $file1 = $client->files()->upload([
        'purpose' => 'assistants',
        'file' => fopen("/Users/dysisx/Documents/assistant/orders-export-2023_10_21_00_32_59.csv", "rb"),
    ]);

    $assistant = $client->assistants()->create([
        'name' => "Retrieval Assistant",
        'instructions' => "VanOnsAssist is a knowledgeable, friendly, and professional AI assistant for the web development company van-ons, specifically designed to help you find the right information about anything concerning the van-ons operations",
        'tools' => [['type' => 'code_interpreter']],
        'model' => 'gpt-3.5-turbo-1106',
        'file_ids' => [$file1->id]
    ]);

    echo $assistant->id;
}

function createThread($client) {
    $thread = $client->threads()->create([]);
    echo $thread->id;
}

function addMessage($client, $threadId, $role, $content) {
    $message = $client->threads()->messages()->create($threadId, [
        'role' => $role,
        'content' => $content
    ]);
    echo $message->id;
}

function getMessages($client, $threadId) {
    $response = $client->threads()->messages()->list($threadId);
    $messages = $response->data;
    $messagesData = [];

    foreach ($messages as $message) {
        $fileId = null; // Initialize fileId

        // Loop through each content item
        foreach ($message->content as $contentItem) {
            if (isset($contentItem->annotations)) {
                foreach ($contentItem->annotations as $annotation) {
                    // Check for file_path annotation and extract file_id
                    if ($annotation->type === 'file_path') {
                        $fileId = $annotation->file_path->file_id;
                        break; // Break if file_id is found
                    }
                }
            }
        }

        $messagesData[] = [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $contentItem->text->value, // Assuming 'text' type content
            'fileId' => $fileId
        ];
    }

    echo json_encode($messagesData);
}


function fetchFileFromOpenAI($fileId, $apiKey, $outputPath) {
    $url = "https://api.openai.com/v1/files/" . $fileId . "/content";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents($outputPath, $response);
}

function getFileIdsFromThread($client, $threadId) {
    $response = $client->threads()->messages()->list($threadId);
    $messages = $response->data;
    $fileIds = [];

    foreach ($messages as $message) {
        if (isset($message->content) && is_array($message->content)) {
            foreach ($message->content as $content) {
                if ($content->type === 'file_path' && isset($content->file_path->file_id)) {
                    $fileIds[] = $content->file_path->file_id;
                }
            }
        }
    }

    return $fileIds;
}

function saveFilesFromThread($client, $threadId, $outputDir) {
    global $apiKey;

    $fileIds = getFileIdsFromThread($client, $threadId);
    foreach ($fileIds as $index => $fileId) {
        $outputPath = $outputDir . '/file_' . uniqid() . '_' . $index . '.txt'; // Dynamic file naming
        fetchFileFromOpenAI($fileId, $apiKey, $outputPath);
        echo "File saved to: " . $outputPath . "\n";
    }
}

function runAssistant($client, $threadId, $assistantId) {
    $run = $client->threads()->runs()->create($threadId, [
        'assistant_id' => $assistantId
    ]);

    echo $run->id;
}

function checkRunStatus($client, $threadId, $runId) {
    $run = $client->threads()->runs()->retrieve($threadId, $runId);
    echo json_encode(['status' => $run->status, 'id' => $run->id]);
}

function deleteThread($client, $threadId) {
    $response = $client->threads()->delete($threadId);
    echo $response->id;
}

function deleteAssistant($client, $assistantId) {
    $response = $client->assistants()->delete($assistantId);
    echo $response->id;
}

// Entry point of the PHP script
if ($argc > 1) {
    $functionName = $argv[1];
    $args = array_slice($argv, 2);
    if (function_exists($functionName)) {
        call_user_func_array($functionName, array_merge([$client], $args));
    } else {
        echo "No function named {$functionName} found.";
    }
} else {
    echo "No function specified to call.";
}

?>
