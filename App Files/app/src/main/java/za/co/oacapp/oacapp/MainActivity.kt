package za.co.oacapp.oacapp

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.util.Log
import androidx.activity.OnBackPressedCallback
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
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
        }, "NativeApp")

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                if (url != null) {
                    view?.loadUrl(url)
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

    private fun askNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

}
