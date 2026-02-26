package za.co.oacapp.oacapp

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.KeyEvent
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.ComponentActivity
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import com.google.android.gms.tasks.Tasks
import com.google.firebase.messaging.FirebaseMessaging
import za.co.oacapp.oacapp.MyFirebaseMessagingService.Companion.EXTRA_NOTIFICATION_LINK

class MainActivity : ComponentActivity() {

    private lateinit var webView: WebView
    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null
    private val baseUrl = "https://oacapp.co.za/"
    private var lastInsets: WindowInsetsCompat? = null

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

        // 1. Set up edge-to-edge display
        WindowCompat.setDecorFitsSystemWindows(window, false)
        
        setContentView(R.layout.activity_main)
        webView = findViewById(R.id.webView)
        
        // Make WebView background transparent to prevent white flash
        webView.setBackgroundColor(Color.TRANSPARENT)

        // 2. Listen for insets changes
        ViewCompat.setOnApplyWindowInsetsListener(webView) { _, insets ->
            lastInsets = insets
            applyInsetsToWebView() // Apply insets whenever they change
            insets
        }
        
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

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                // 3. Apply insets after page has loaded
                applyInsetsToWebView()
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

        askNotificationPermission()
    }

    private fun applyInsetsToWebView() {
        lastInsets?.let {
            val systemBars = it.getInsets(WindowInsetsCompat.Type.systemBars())
            
            // Use raw pixel values, which is what CSS `px` units expect.
            val top = systemBars.top
            val bottom = systemBars.bottom
            val left = systemBars.left
            val right = systemBars.right

            val script = """
                (function() {
                    const styleId = 'android-insets-style';
                    let styleElement = document.getElementById(styleId);
                    if (!styleElement) {
                        styleElement = document.createElement('style');
                        styleElement.id = styleId;
                        document.head.appendChild(styleElement);
                    }
                    styleElement.innerHTML = `
                        :root {
                            --safe-area-inset-top: ${top}px;
                            --safe-area-inset-bottom: ${bottom}px;
                            --safe-area-inset-left: ${left}px;
                            --safe-area-inset-right: ${right}px;
                        }
                    `;
                })();
            """.trimIndent()
            webView.evaluateJavascript(script, null)
        }
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

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode == KeyEvent.KEYCODE_BACK) {
            if (webView.canGoBack()) {
                webView.goBack()
                return true
            }
            if (webView.url != baseUrl) {
                webView.loadUrl(baseUrl)
                return true
            }
        }
        return super.onKeyDown(keyCode, event)
    }
}