// assets/app.js
// Shared JS Helpers

document.addEventListener('DOMContentLoaded', () => {
    // Flash message dismissal
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 3000);
    });
});
