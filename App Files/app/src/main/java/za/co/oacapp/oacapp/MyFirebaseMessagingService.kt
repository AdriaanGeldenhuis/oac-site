package za.co.oacapp.oacapp

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.RingtoneManager
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import java.util.concurrent.atomic.AtomicInteger

class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        Log.d(TAG, "Message received from: ${remoteMessage.from}")
        Log.d(TAG, "Data payload: ${remoteMessage.data}")
        
        // Get title and body from DATA payload (not notification payload)
        val title = remoteMessage.data[KEY_TITLE] ?: getString(R.string.default_notification_title)
        val body = remoteMessage.data[KEY_BODY] ?: getString(R.string.default_notification_body)
        val link = remoteMessage.data[KEY_LINK] ?: "/notifications/notifications.php"

        Log.d(TAG, "Creating notification - Title: $title, Link: $link")
        showNotification(title, body, link)
    }

    override fun onNewToken(token: String) {
        Log.d(TAG, "Refreshed token: $token")
        // TODO: If you want to send messages to this application instance or
        // manage this apps subscriptions on the server side, send the
        // FCM registration token to your app server.
        sendRegistrationToServer(token)
    }

    private fun sendRegistrationToServer(token: String?) {
        // Implement this method to send token to your app server.
        Log.d(TAG, "sendRegistrationTokenToServer($token)")
    }

    private fun showNotification(title: String, body: String, link: String) {
        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra(EXTRA_NOTIFICATION_LINK, link)
            addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }

        val id = notificationId.incrementAndGet()

        val pendingIntent = PendingIntent.getActivity(
            this,
            id,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val notificationBuilder = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(id, notificationBuilder.build())
    }

    companion object {
        private const val TAG = "MyFirebaseMsgService"
        const val CHANNEL_ID = "oac_notifications"
        private const val KEY_TITLE = "title"
        private const val KEY_BODY = "body"
        private const val KEY_LINK = "click_url"
        const val EXTRA_NOTIFICATION_LINK = "notification_link"
        private val notificationId = AtomicInteger(0)
    }
}