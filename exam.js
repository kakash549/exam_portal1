// exam.js
document.addEventListener('DOMContentLoaded', () => {
    // Enter full-screen mode
    document.documentElement.requestFullscreen().catch(err => {
        console.error('Failed to enter fullscreen:', err);
        alert('Please enable fullscreen mode to start the exam.');
    });

    // Re-enter full-screen if the user exits
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            alert('Full-screen mode is required for the exam!');
            document.documentElement.requestFullscreen().catch(err => {
                console.error('Failed to re-enter fullscreen:', err);
            });
        }
    });

    // Prevent tab switching
    window.onblur = () => {
        alert('Tab switching detected! This violation has been logged.');
        // Optionally auto-submit: document.getElementById('exam-form').submit();
    };

    // Disable right-click
    document.oncontextmenu = () => {
        alert('Right-click is disabled during the exam.');
        return false;
    };

    // Disable keyboard shortcuts (Ctrl, Alt, Escape)
    document.onkeydown = (e) => {
        if (e.ctrlKey || e.altKey || e.key === 'Escape') {
            alert('Keyboard shortcuts are disabled during the exam.');
            e.preventDefault();
            return false;
        }
    };

    // Detect screen-sharing or multiple monitors
    if (window.screen.width !== window.innerWidth || window.screen.height !== window.innerHeight) {
        alert('Screen size mismatch detected (possible screen sharing)! This violation has been logged.');
        // Optionally auto-submit: document.getElementById('exam-form').submit();
    }
});