var intervalId; // Declare intervalId at a higher scope

function startAssistantRun() {
    $.ajax({
        url: '/start-run',
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            var runId = response.runId;
            initiateStatusCheck(runId);
        },
        error: function(error) {
            console.error('Error starting assistant run:', error);
        }
    });
}

function checkRunStatus(runId) {
    $.ajax({
        url: '/check-run-status',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: { runId: runId },
        success: function(response) {
            if (response.status !== 'in_progress') {
                clearInterval(intervalId);
                if (response.status === 'completed') {
                    fetchAndDisplayMessages();
                } else {
                    updateMessageArea('<p>Run ended with status: ' + response.status + '</p>');
                }
            }
        },
        error: function(error) {
            console.error('Error checking run status:', error);
            clearInterval(intervalId);
        }
    });
}

function initiateStatusCheck(runId) {
    intervalId = setInterval(function() {
        checkRunStatus(runId);
    }, 2000);
}

function submitMessage() {
    var messageInput = $('#message');
    var message = messageInput.val();
    updateMessageArea('<p><strong>User:</strong> ' + message + '</p>', true);
    updateMessageArea('<p>Processing your request...</p>', true);

    $.ajax({
        url: '/submit-message',
        type: 'POST',
        data: { 
            message: message,
            _token: $('input[name="_token"]').val()
        },
        success: function() {
            messageInput.val('');
            startAssistantRun();
        },
        error: function(error) {
            console.error('Error submitting message:', error);
            updateMessageArea('<p>Error submitting message. Please try again.</p>', true);
        }
    });
}

function updateMessageArea(message, append = false) {
    var messageArea = document.getElementById('messages');
    if (append) {
        var newMessage = document.createElement('div');
        newMessage.innerHTML = message;
        messageArea.appendChild(newMessage);
        messageArea.scrollTop = messageArea.scrollHeight;
    } else {
        messageArea.innerHTML = message;
    }
}

function fetchAndDisplayMessages() {
    $.ajax({
        url: '/get-messages',
        type: 'GET',
        success: function(response) {
            var messageContent = '<p><strong>Messages:</strong></p>';
            response.reverse().forEach(function(message) {
                var modifiedContent = message.content;

                if (message.file_ids && message.file_ids.length > 0) {
                    modifiedContent += "<p>Attached Files: ";
                    message.file_ids.forEach(function(fileId, index) {
                        modifiedContent += `<button onclick="downloadFile('${fileId}')">File ${index + 1}</button> `;
                    });
                    modifiedContent += "</p>";
                }

                messageContent += '<p><strong>' + message.role + ':</strong> ' + modifiedContent + '</p>';
            });
            updateMessageArea(messageContent);
        },
        error: function(error) {
            console.error('Error fetching messages:', error);
            updateMessageArea('<p>Error fetching messages. Please try again.</p>');
        }
    });
}

function downloadFile(fileId) {
    window.location.href = '/download-file/' + fileId; // Adjust as per your file download endpoint
}

function downloadFiles() {
    $.ajax({
        url: '/download-files',
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Files downloaded successfully');
            // Handle the response, such as providing a link to the downloaded file
        },
        error: function(error) {
            console.error('Error downloading files:', error);
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('messageForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitMessage();
    });
});
