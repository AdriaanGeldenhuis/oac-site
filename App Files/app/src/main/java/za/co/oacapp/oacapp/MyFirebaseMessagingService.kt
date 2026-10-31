package za.co.oacapp.oacapp

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.RingtoneManager
import android.util.Log
import android.webkit.CookieManager
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.atomic.AtomicInteger

class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        Log.d(TAG, "Message received from: ${remoteMessage.from}")
        Log.d(TAG, "Data payload: ${remoteMessage.data}")
        
        // Get title and body from DATA payload (not notification payload)
        val title = remoteMessage.data[KEY_TITLE] ?: getString(R.string.default_notification_title)
        val body = remoteMessage.data[KEY_BODY] ?: getString(R.string.default_notification_body)
        val link = remoteMessage.data[KEY_LINK] ?: "/notifications/notifications.php"
        // The stored notification's id (sent with every notification the site
        // keeps): the tray shows it under that id, so the site can take it
        // down here too once it is opened there (MainActivity.clearNotification)
        val serverId = remoteMessage.data[KEY_NOTIFICATION_ID]?.toIntOrNull()?.takeIf { it > 0 }

        Log.d(TAG, "Creating notification - Title: $title, Link: $link, Id: $serverId")
        showNotification(title, body, link, serverId)
    }

    override fun onNewToken(token: String) {
        Log.d(TAG, "Refreshed token: $token")
        sendRegistrationToServer(token)
    }

    /**
     * Register the (rotated) FCM token with the server immediately, using the
     * WebView's session cookie for authentication. Without this, a token
     * rotation would silently stop push delivery until the user next opens
     * the app (when the web bridge re-registers it).
     */
    private fun sendRegistrationToServer(token: String?) {
        if (token.isNullOrEmpty()) return

        Thread {
            try {
                val cookies = CookieManager.getInstance().getCookie(SITE_URL)
                if (cookies.isNullOrEmpty()) {
                    Log.d(TAG, "No session cookie yet; web bridge will register the token on next app open")
                    return@Thread
                }

                val url = URL("$SITE_URL/admin/api/fcm/register_token.php")
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.setRequestProperty("Content-Type", "application/json")
                conn.setRequestProperty("Cookie", cookies)
                conn.connectTimeout = 15000
                conn.readTimeout = 15000
                conn.doOutput = true

                val body = JSONObject()
                    .put("token", token)
                    .put("device_info", "Android App (token refresh)")
                    .toString()

                conn.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
                Log.d(TAG, "Token registration response: ${conn.responseCode}")
                conn.disconnect()
            } catch (e: Exception) {
                Log.e(TAG, "Failed to register token with server", e)
            }
        }.start()
    }

    private fun showNotification(title: String, body: String, link: String, serverId: Int?) {
        // One tray id per notification: the server's (positive), or a fresh
        // negative one for a push without it. Never reused while the other may
        // still be in the tray, so a new push never replaces an older one or
        // takes over its tap (the PendingIntent below is keyed by this id too).
        val id = serverId ?: -localIds.incrementAndGet()

        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra(EXTRA_NOTIFICATION_LINK, link)
            putExtra(EXTRA_NOTIFICATION_TRAY_ID, id)
            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            id,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val notificationBuilder = NotificationCompat.Builder(this, CHANNEL_ID)
            // The status bar draws only the icon's shape: the full-colour
            // launcher icon would be a plain blob, the church is recognisable
            .setSmallIcon(R.drawable.baseline_church_24)
            .setContentTitle(title)
            .setContentText(body)
            // Expandable so long bodies (e.g. the daily thought) are fully readable
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            // Tapped: it leaves the tray (and open.php takes it down on the site)
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(id, notificationBuilder.build())
    }

    companion object {
        private const val TAG = "MyFirebaseMsgService"
        private const val SITE_URL = "https://oacapp.co.za"
        const val CHANNEL_ID = "oac_notifications"
        private const val KEY_TITLE = "title"
        private const val KEY_BODY = "body"
        private const val KEY_LINK = "click_url"
        private const val KEY_NOTIFICATION_ID = "notification_id"
        const val EXTRA_NOTIFICATION_LINK = "notification_link"
        const val EXTRA_NOTIFICATION_TRAY_ID = "notification_tray_id"
        // Pushes without a server id: counted on from the clock (seconds), so
        // after the app restarts the new ids do not meet ones still in the tray
        private val localIds = AtomicInteger((System.currentTimeMillis() / 1000 % 1_000_000_000L).toInt())
    }
}