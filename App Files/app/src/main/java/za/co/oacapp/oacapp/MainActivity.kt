package za.co.oacapp.oacapp

import android.Manifest
import android.app.DownloadManager
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.CalendarContract
import android.util.Log
import androidx.activity.OnBackPressedCallback
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import com.google.android.gms.tasks.Tasks
import com.google.firebase.messaging.FirebaseMessaging
import za.co.oacapp.oacapp.MyFirebaseMessagingService.Companion.EXTRA_NOTIFICATION_LINK

class MainActivity : ComponentActivity() {

    private lateinit var webView: WebView
    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null
    private val baseUrl = "https://oacapp.co.za/"

    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.GetMultipleContents()
    ) { uris ->
        fileUploadCallback?.onReceiveValue(uris.toTypedArray())
        fileUploadCallback = null
    }

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted: Boolean ->
        // We can handle the result here if needed
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        Log.d("MainActivity", "onCreate called")

        // Let the system handle insets — the WebView will be placed
        // below the status bar and above the navigation bar automatically.
        // The status/nav bar backgrounds use the windowBackground (black).
        setContentView(R.layout.activity_main)
        webView = findViewById(R.id.webView)

        webView.setBackgroundColor(Color.BLACK)

        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.settings.allowFileAccess = true
        webView.settings.allowContentAccess = true

        webView.addJavascriptInterface(object {
            @JavascriptInterface
            fun getFcmToken(): String {
                return try {
                    val task = FirebaseMessaging.getInstance().token
                    val token = Tasks.await(task)
                    Log.d("MainActivity", "FCM Token: $token")
                    token
                } catch (e: Exception) {
                    Log.e("MainActivity", "Error getting FCM token", e)
                    ""
                }
            }

            @JavascriptInterface
            fun requestNotificationPermission(): Boolean {
                askNotificationPermission()
                return true
            }

            @JavascriptInterface
            fun logDebug(message: String) {
                Log.d("WebViewBridge", message)
            }

            /**
             * Calendar / Gospel Media "add to my phone's calendar": opens the
             * phone's own "new event" screen, filled in. Times are UTC
             * milliseconds (as strings, so nothing is lost on the way).
             * Returns false when no calendar app can take it; the page then
             * downloads an .ics file instead.
             */
            @JavascriptInterface
            fun addToCalendar(
                title: String,
                beginMillis: String,
                endMillis: String,
                allDay: Boolean,
                location: String,
                description: String
            ): Boolean {
                val begin = beginMillis.toLongOrNull() ?: return false
                val end = endMillis.toLongOrNull() ?: (begin + 3_600_000L)
                val intent = Intent(Intent.ACTION_INSERT).apply {
                    data = CalendarContract.Events.CONTENT_URI
                    putExtra(CalendarContract.EXTRA_EVENT_BEGIN_TIME, begin)
                    putExtra(CalendarContract.EXTRA_EVENT_END_TIME, end)
                    putExtra(CalendarContract.EXTRA_EVENT_ALL_DAY, allDay)
                    putExtra(CalendarContract.Events.TITLE, title)
                    putExtra(CalendarContract.Events.EVENT_LOCATION, location)
                    putExtra(CalendarContract.Events.DESCRIPTION, description)
                }
                if (intent.resolveActivity(packageManager) == null) return false
                runOnUiThread {
                    try {
                        startActivity(intent)
                    } catch (e: Exception) {
                        Log.w("MainActivity", "No calendar app", e)
                    }
                }
                return true
            }
        }, "NativeApp")

        // Files the site hands out (calendar PDF / Excel / .ics) go to the
        // phone's Downloads, with the session cookie so the server knows who
        // asks. Without this the WebView silently ignores every download.
        webView.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            startDownload(url, userAgent, contentDisposition, mimeType)
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                if (url == null) return true
                val scheme = Uri.parse(url).scheme?.lowercase()
                if (scheme == "http" || scheme == "https") {
                    view?.loadUrl(url)
                    return true
                }
                // Phone, e-mail, SMS, WhatsApp and map links (e.g. in the
                // teaching of the month) belong to other apps; the WebView
                // itself can only show an "unknown URL scheme" error for them
                if (scheme != null && scheme in setOf("tel", "mailto", "sms", "whatsapp", "geo")) {
                    try {
                        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                    } catch (e: Exception) {
                        Log.w("MainActivity", "No app to open $url", e)
                    }
                }
                return true
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                fileUploadCallback = filePathCallback
                fileChooserLauncher.launch("*/*")
                return true
            }
        }

        if (savedInstanceState == null) {
            if (!handleNotificationIntent(intent)) {
                webView.loadUrl(baseUrl)
            }
        } else {
            webView.restoreState(savedInstanceState)
        }

        // Handle back button: navigate within WebView instead of exiting app.
        // onKeyDown(KEYCODE_BACK) is ignored on API 33+ / predictive back;
        // OnBackPressedDispatcher is the modern replacement.
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else if (webView.url != baseUrl) {
                    webView.loadUrl(baseUrl)
                } else {
                    // Already on home page — let the system exit the app
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        askNotificationPermission()
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleNotificationIntent(intent)
    }

    private fun handleNotificationIntent(intent: Intent): Boolean {
        val link = intent.getStringExtra(EXTRA_NOTIFICATION_LINK)
        if (!link.isNullOrEmpty()) {
            val url = if (link.startsWith("http")) link else "https://oacapp.co.za$link"
            webView.loadUrl(url)
            intent.removeExtra(EXTRA_NOTIFICATION_LINK)
            return true
        }
        return false
    }

    private fun startDownload(url: String, userAgent: String?, contentDisposition: String?, mimeType: String?) {
        val scheme = Uri.parse(url).scheme?.lowercase()
        if (scheme != "http" && scheme != "https") {
            // blob: / data: links only exist inside the page
            Log.w("MainActivity", "Cannot download $scheme link")
            return
        }
        try {
            val fileName = URLUtil.guessFileName(url, contentDisposition, mimeType)
            val request = DownloadManager.Request(Uri.parse(url)).apply {
                CookieManager.getInstance().getCookie(url)?.let { addRequestHeader("Cookie", it) }
                if (!userAgent.isNullOrEmpty()) addRequestHeader("User-Agent", userAgent)
                if (!mimeType.isNullOrEmpty()) setMimeType(mimeType)
                setTitle(fileName)
                setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName)
                } else {
                    // Older Android: the app's own folder needs no storage permission
                    setDestinationInExternalFilesDir(this@MainActivity, Environment.DIRECTORY_DOWNLOADS, fileName)
                }
            }
            val manager = getSystemService(DOWNLOAD_SERVICE) as DownloadManager
            manager.enqueue(request)
            Toast.makeText(this, fileName, Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e("MainActivity", "Download failed", e)
        }
    }

    private fun askNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

}
