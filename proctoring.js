// proctoring.js
let audioViolationCount = 0;
let videoViolationCount = 0;
const VOLUME_THRESHOLD = 50; // Adjust as needed
const VIOLATION_LIMIT = 3; // Number of violations before alerting

// Share violation count with test_exam.php
function incrementGlobalViolationCount(type) {
    window.violationCount = (window.violationCount || 0) + 1;
    showProctoringMessage(`${type.replace('_', ' ')} detected! This violation has been logged.`);
    if (window.violationCount >= window.maxViolations) {
        alert("Too many violations! Exam terminated.");
        if (typeof submitExam === 'function') {
            submitExam();
        } else {
            console.error('submitExam function not found. Ensure proctoring.js is loaded in test_exam.php.');
        }
    }
}

function showProctoringMessage(message) {
    const msg = document.createElement('div');
    msg.className = 'proctoring-message';
    msg.innerHTML = `${message} <button onclick="this.parentElement.style.display='none'">OK</button>`;
    document.body.appendChild(msg);
    msg.style.display = 'block';
    setTimeout(() => msg.style.display = 'none', 5000);
}

// Function to log violations (defined globally)
function logViolation(type) {
    const examId = new URLSearchParams(window.location.search).get('exam_id');
    // userId should be set by test_exam.php as a global variable
    const userId = window.currentUserId || 0;
    if (!userId) {
        console.error('User ID not found. Ensure currentUserId is set in test_exam.php.');
        return;
    }
    fetch('../user/log_violation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${encodeURIComponent(userId)}&exam_id=${encodeURIComponent(examId)}&violation_type=${encodeURIComponent(type)}`
    }).catch(err => console.error('Failed to log violation:', err));
}

navigator.mediaDevices.getUserMedia({ video: true, audio: true })
    .then(stream => {
        const video = document.getElementById('webcam');
        if (!video) {
            console.error('Webcam element not found.');
            logViolation('webcam_not_found');
            return;
        }
        video.srcObject = stream;

        // Audio proctoring
        const audioContext = new AudioContext();
        const analyser = audioContext.createAnalyser();
        const microphone = audioContext.createMediaStreamSource(stream);
        microphone.connect(analyser);
        analyser.fftSize = 2048;
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        const audioCheckInterval = setInterval(() => {
            analyser.getByteFrequencyData(dataArray);
            const volume = dataArray.reduce((a, b) => a + b) / bufferLength;
            if (volume > VOLUME_THRESHOLD) {
                audioViolationCount++;
                console.log(`Audio anomaly detected (volume: ${volume})`);
                if (audioViolationCount >= VIOLATION_LIMIT) {
                    logViolation('audio_anomaly');
                    incrementGlobalViolationCount('audio_anomaly');
                    audioViolationCount = 0;
                }
            }
        }, 1000);

        // Video proctoring with face detection
        async function loadFaceDetection() {
            if (typeof faceapi === 'undefined') {
                console.error('face-api.js not loaded.');
                logViolation('face_api_not_loaded');
                return;
            }
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri('/exam_portal1/assets/face-api-models');
                await faceapi.nets.faceLandmark68Net.loadFromUri('/exam_portal1/assets/face-api-models');
                const videoCheckInterval = setInterval(async () => {
                    const options = new faceapi.TinyFaceDetectorOptions();
                    const faces = await faceapi.detectAllFaces(video, options);
                    if (faces.length > 1) {
                        videoViolationCount++;
                        console.log('Multiple faces detected');
                        if (videoViolationCount >= VIOLATION_LIMIT) {
                            logViolation('multiple_faces');
                            incrementGlobalViolationCount('multiple_faces');
                            videoViolationCount = 0;
                        }
                    } else if (faces.length === 0) {
                        videoViolationCount++;
                        console.log('No face detected');
                        if (videoViolationCount >= VIOLATION_LIMIT) {
                            logViolation('no_face');
                            incrementGlobalViolationCount('no_face');
                            videoViolationCount = 0;
                        }
                    }
                }, 2000);

                window.addEventListener('beforeunload', () => {
                    clearInterval(videoCheckInterval);
                });
            } catch (err) {
                console.error('Face detection failed:', err);
                logViolation('face_detection_failed');
            }
        }
        loadFaceDetection();

        window.addEventListener('beforeunload', () => {
            clearInterval(audioCheckInterval);
            stream.getTracks().forEach(track => track.stop());
        });
    })
    .catch(err => {
        alert('Webcam or microphone access denied. Please grant access to continue the exam: ' + err.message);
        console.error('Media access error:', err);
        logViolation('webcam_access_denied');
    });