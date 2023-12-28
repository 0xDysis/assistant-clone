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
        // Process content as before
        $content = $message->content;
        if (is_array($content)) {
            $content = json_encode($content);
        }
        $contentJson = json_decode($content, true);
        $messageText = $contentJson[0]['text']['value'];

        // Initialize an array for file IDs
        $fileIds = [];

        // Check if the message has file IDs and retrieve details for each file ID
        if (isset($message->file_ids) && is_array($message->file_ids)) {
            foreach ($message->file_ids as $fileId) {
                $fileResponse = $client->threads()->messages()->files()->retrieve(
                    threadId: $threadId,
                    messageId: $message->id,
                    fileId: $fileId,
                );

                // Check if fileResponse is successful and contains the file ID
                if ($fileResponse && isset($fileResponse->id)) {
                    array_push($fileIds, $fileResponse->id);
                }
            }
        }

        $messageDict = [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $messageText,
            'file_ids' => $fileIds // Include the retrieved file IDs
        ];
        array_push($messagesData, $messageDict);
    }

    echo json_encode($messagesData); 
}



function getFileIdsFromThread($client, $threadId) {
    $response = $client->threads()->messages()->list($threadId);
    $messages = $response->data;
    $fileIds = [];

    foreach ($messages as $message) {
        if (isset($message->file_ids) && is_array($message->file_ids)) {
            foreach ($message->file_ids as $fileId) {
                array_push($fileIds, $fileId);
            }
        }
    }

    return $fileIds;
}


function getFileContent($client, $fileId, $outputPath) {
    $fileContent = $client->files()->content($fileId);
    
    // Assuming $fileContent is the raw file data
    file_put_contents($outputPath, $fileContent);

    echo "File saved to: $outputPath";
}
function downloadFilesFromThread($client, $threadId, $outputPath) {
    $fileIds = getFileIdsFromThread($client, $threadId);

    foreach ($fileIds as $fileId) {
        try {
            // Retrieve file details
            $fileResponse = $client->threads()->messages()->files()->retrieve(
                threadId: $threadId,
                messageId: '', // You need to pass the correct messageId here
                fileId: $fileId,
            );

            // Proceed only if fileResponse is valid
            if ($fileResponse && isset($fileResponse->id)) {
                // Define the specific path for each file
                $specificOutputPath = $outputPath . '/' . $fileResponse->id;

                // Ensure the directory exists
                if (!file_exists(dirname($specificOutputPath))) {
                    mkdir(dirname($specificOutputPath), 0777, true);
                }

                // Download and save the file content
                $fileContent = $client->files()->content($fileId);
                file_put_contents($specificOutputPath, $fileContent);
                echo "File saved to: $specificOutputPath";
            } else {
                echo "Error: File details not found in the response.";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        }
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


if ($argc > 1) {
    $functionName = $argv[1];
    $args = array_slice($argv, 2);

    if ($functionName === 'getFileIdsFromThread') {
        $fileIds = call_user_func_array($functionName, array_merge([$client], $args));
        echo json_encode($fileIds);
    } elseif (in_array($functionName, ['getFileContent', 'downloadFile'])) {
        call_user_func_array($functionName, array_merge([$client], $args));
    } elseif (function_exists($functionName)) {
        call_user_func_array($functionName, array_merge([$client], $args));
    } else {
        echo "No function named {$functionName} found.";
    }
} else {
    echo "No function specified to call.";
}


?>