package za.co.oacapp.oacapp

import android.Manifest
import android.app.DownloadManager
import android.app.NotificationManager
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
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.webkit.ServiceWorkerClientCompat
import androidx.webkit.ServiceWorkerControllerCompat
import androidx.webkit.WebViewFeature
import com.google.android.gms.tasks.Tasks
import com.google.firebase.messaging.FirebaseMessaging
import za.co.oacapp.oacapp.MyFirebaseMessagingService.Companion.EXTRA_NOTIFICATION_LINK
import za.co.oacapp.oacapp.MyFirebaseMessagingService.Companion.EXTRA_NOTIFICATION_TRAY_ID
import java.util.concurrent.TimeUnit

class MainActivity : ComponentActivity() {

    private lateinit var webView: WebView
    private lateinit var offlineAssets: OfflineAssets
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
        // No page needs the phone's files: uploads go through the file
        // chooser, and our own pages in file:///android_asset load anyway
        webView.settings.allowFileAccess = false
        webView.settings.allowContentAccess = true

        // The Bible and the hymn book inside the app (see OfflineAssets). The
        // site's service worker (/sw.js) fetches through its own client, so
        // both the page and the worker are answered from the app.
        offlineAssets = OfflineAssets(applicationContext)
        if (WebViewFeature.isFeatureSupported(WebViewFeature.SERVICE_WORKER_BASIC_USAGE) &&
            WebViewFeature.isFeatureSupported(WebViewFeature.SERVICE_WORKER_SHOULD_INTERCEPT_REQUEST)
        ) {
            ServiceWorkerControllerCompat.getInstance().setServiceWorkerClient(object : ServiceWorkerClientCompat() {
                override fun shouldInterceptRequest(request: WebResourceRequest): WebResourceResponse? =
                    offlineAssets.intercept(request)
            })
        }

        webView.addJavascriptInterface(object {
            @JavascriptInterface
            fun getFcmToken(): String {
                return try {
                    // The page waits for this answer: never longer than a few
                    // seconds (without internet a new token may never come)
                    val task = FirebaseMessaging.getInstance().token
                    val token = Tasks.await(task, FCM_TOKEN_WAIT_SECONDS, TimeUnit.SECONDS)
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
             * A notification opened (taken down) on the site leaves the
             * phone's tray too. The tray shows each one under the site's
             * own id (MyFirebaseMessagingService).
             */
            @JavascriptInterface
            fun clearNotification(id: Int) {
                if (id > 0) trayManager().cancel(id)
            }

            /** The notifications page is on screen, or all is read: the tray's copies go */
            @JavascriptInterface
            fun clearAllNotifications() {
                trayManager().cancelAll()
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
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val uri = request?.url ?: return true
                val scheme = uri.scheme?.lowercase()
                if (scheme == "http" || scheme == "https") {
                    // Our own site stays in the app, and the WebView follows
                    // its redirects itself: one history entry per page, so
                    // Back never lands on an address that only redirects
                    if (uri.host?.lowercase() in OfflineAssets.HOSTS || !request.isForMainFrame) return false
                    // Anyone else's site (a link in a teaching or on the
                    // privacy page) opens in the phone's browser: the app has
                    // no address bar to leave it by. No browser: show it here.
                    return openOutside(uri)
                }
                // Phone, e-mail, SMS, WhatsApp and map links (e.g. in the
                // teaching of the month) belong to other apps; the WebView
                // itself can only show an "unknown URL scheme" error for them
                if (scheme != null && scheme in OUTSIDE_SCHEMES) openOutside(uri)
                return true
            }

            override fun shouldInterceptRequest(view: WebView?, request: WebResourceRequest?): WebResourceResponse? =
                request?.let { offlineAssets.intercept(it) }

            // A page of ours could not load at all for want of a connection
            // (no internet, no data left, the site out of reach), and the
            // site's offline copy does not cover it. Show our own page instead
            // of the WebView's error; it offers to try that address again.
            // Other errors (Back to a page that was a sent form, a redirect
            // loop) are not about the connection: the WebView says those.
            override fun onReceivedError(view: WebView?, request: WebResourceRequest?, error: WebResourceError?) {
                super.onReceivedError(view, request, error)
                if (request == null || !request.isForMainFrame) return
                if (request.url.host?.lowercase() !in OfflineAssets.HOSTS) return
                if (error != null && error.errorCode !in CONNECTION_ERRORS && offlineAssets.isOnline()) return
                view?.loadUrl(NO_CONNECTION_PAGE + "?u=" + Uri.encode(request.url.toString()))
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
                // On the "no connection" page the entry before it is the
                // address that failed: step over it, or that page would only
                // fail again
                if (webView.url?.startsWith(NO_CONNECTION_PAGE) == true) {
                    if (webView.canGoBackOrForward(-2)) {
                        webView.goBackOrForward(-2)
                    } else {
                        leaveApp()
                    }
                    return
                }
                if (webView.canGoBack()) {
                    webView.goBack()
                } else if (!isStartPage(webView.url)) {
                    webView.loadUrl(baseUrl)
                } else {
                    // Already on the home (or login) page — let the system exit the app
                    leaveApp()
                }
            }

            // The system's own Back: closes the app, or on Android 12+ only
            // moves it to the background. The app then lives on, so this
            // callback must be on again for the next Back.
            private fun leaveApp() {
                isEnabled = false
                onBackPressedDispatcher.onBackPressed()
                isEnabled = true
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

    /**
     * A tapped notification: open where it points. Its link goes through
     * /notifications/open.php, which takes it down on the site first.
     */
    private fun handleNotificationIntent(intent: Intent): Boolean {
        // Opened again from Recents: that tap was handled long ago, and
        // opening it once more would leave the page the member was on
        if ((intent.flags and Intent.FLAG_ACTIVITY_LAUNCHED_FROM_HISTORY) != 0) return false
        val link = intent.getStringExtra(EXTRA_NOTIFICATION_LINK)
        if (!link.isNullOrEmpty()) {
            // Out of the tray (autoCancel does it too, but not on every phone)
            val trayId = intent.getIntExtra(EXTRA_NOTIFICATION_TRAY_ID, 0)
            if (trayId != 0) trayManager().cancel(trayId)
            val url = if (link.startsWith("http")) link else "https://oacapp.co.za$link"
            webView.loadUrl(url)
            intent.removeExtra(EXTRA_NOTIFICATION_LINK)
            intent.removeExtra(EXTRA_NOTIFICATION_TRAY_ID)
            return true
        }
        return false
    }

    private fun trayManager(): NotificationManager =
        getSystemService(NOTIFICATION_SERVICE) as NotificationManager

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

    /** Open a link in the app that handles it (browser, phone, mail, ...); false when there is none */
    private fun openOutside(uri: Uri): Boolean = try {
        startActivity(Intent(Intent.ACTION_VIEW, uri))
        true
    } catch (e: Exception) {
        Log.w("MainActivity", "No app to open $uri", e)
        false
    }

    /**
     * Where Back closes the app: the home page (/ only redirects to
     * /welcome.php, so that is where the app really is) and the login page,
     * which / redirects to when nobody is signed in
     */
    private fun isStartPage(url: String?): Boolean {
        val uri = url?.let { Uri.parse(it) } ?: return true
        if (uri.host?.lowercase() !in OfflineAssets.HOSTS) return false
        return uri.path.orEmpty().ifEmpty { "/" } in START_PATHS
    }

    companion object {
        private const val NO_CONNECTION_PAGE = "file:///android_asset/no_connection.html"
        private const val FCM_TOKEN_WAIT_SECONDS = 5L
        private val START_PATHS = setOf("/", "/index.php", "/welcome.php", "/login.php")
        private val OUTSIDE_SCHEMES = setOf("tel", "mailto", "sms", "whatsapp", "geo")
        // The WebView's errors that mean the site could not be reached
        private val CONNECTION_ERRORS = setOf(
            WebViewClient.ERROR_HOST_LOOKUP,
            WebViewClient.ERROR_CONNECT,
            WebViewClient.ERROR_TIMEOUT,
            WebViewClient.ERROR_IO
        )
    }

    private fun askNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

}
