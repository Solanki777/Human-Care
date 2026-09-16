        // Get-or-create the chat room for this appointment, then jump into it.
        // If a room already exists we're handed its id directly (no extra
        // request needed); otherwise we ask the API to create one.
        function openChatForAppointment(appointmentId, existingRoomId) {
            if (existingRoomId) {
                window.location.href = `doctor_msg.php?room_id=${existingRoomId}`;
                return;
            }

            const formData = new FormData();
            formData.append('action', 'get_or_create_room');
            formData.append('appointment_id', appointmentId);

            fetch('msg_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.chatRoom) {
                    window.location.href = `doctor_msg.php?room_id=${data.chatRoom.id}`;
                } else {
                    alert(data.error || 'Unable to open chat. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error opening chat:', error);
                alert('Error opening chat. Please try again.');
            });
        }