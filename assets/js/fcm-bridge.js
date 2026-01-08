/**
 * FCM Bridge - Connects web app with native Android FCM
 * ======================================================
 *
 * This script handles communication between the web app and the native
 * Android app for push notifications via Firebase Cloud Messaging.
 *
 * Native side must expose:
 * - window.NativeApp.getFcmToken() - Returns the FCM token
 * - window.NativeApp.requestNotificationPermission() - Requests permission
 */

const FCMBridge = {

    // Check if running in native app
    isNativeApp: function() {
        return typeof window.NativeApp !== 'undefined';
    },

    // Get FCM token from native app
    getToken: function() {
        if (!this.isNativeApp()) {
            console.log('Not running in native app');
            return null;
        }

        try {
            return window.NativeApp.getFcmToken();
        } catch (e) {
            console.error('Error getting FCM token:', e);
            return null;
        }
    },

    // Request notification permission
    requestPermission: function() {
        if (!this.isNativeApp()) {
            return false;
        }

        try {
            return window.NativeApp.requestNotificationPermission();
        } catch (e) {
            console.error('Error requesting notification permission:', e);
            return false;
        }
    },

    // Register token with server
    registerToken: async function(token) {
        if (!token) {
            console.log('No token to register');
            return false;
        }

        try {
            const response = await fetch('/admin/api/fcm/register_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: token,
                    device_info: navigator.userAgent
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log('FCM token registered successfully');
                localStorage.setItem('fcm_token_registered', 'true');
                localStorage.setItem('fcm_token', token);
                return true;
            } else {
                console.error('Failed to register token:', data.error);
                return false;
            }
        } catch (e) {
            console.error('Error registering FCM token:', e);
            return false;
        }
    },

    // Initialize - get token and register
    init: async function() {
        if (!this.isNativeApp()) {
            console.log('FCM Bridge: Not running in native app, skipping');
            return;
        }

        console.log('FCM Bridge: Initializing...');

        // Request permission first
        this.requestPermission();

        // Small delay to ensure native side is ready
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Get and register token
        const token = this.getToken();

        if (token) {
            const lastToken = localStorage.getItem('fcm_token');

            // Only register if token changed or never registered
            if (token !== lastToken) {
                console.log('FCM Bridge: New token detected, registering...');
                await this.registerToken(token);
            } else {
                console.log('FCM Bridge: Token already registered');
            }
        } else {
            console.log('FCM Bridge: No token available yet');
        }
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Delay init slightly to ensure native bridge is ready
    setTimeout(() => {
        FCMBridge.init();
    }, 500);
});

// Also expose globally for manual calls
window.FCMBridge = FCMBridge;
