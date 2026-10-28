package za.co.oacapp.oacapp

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.util.Log
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import org.json.JSONObject

/**
 * The Bible and the hymn book travel inside the app (build.gradle.kts
 * copies them from the website's folders at build time), so nobody has to
 * download them first.
 *
 * When the page or the site's service worker asks for, say,
 *   https://oacapp.co.za/bible/bibles/af_1933_53.json?v=<hash>
 * and that hash is the one of the copy inside the app, the answer comes
 * from the app: instant, no data used. Offline the app's copy is used
 * whatever the hash. Otherwise the server has a newer file, and the
 * request goes out as usual.
 */
class OfflineAssets(private val context: Context) {

    private class Entry(val asset: String, val version: String, val size: Long, val type: String)

    // Read once, on first use (shouldInterceptRequest runs off the UI thread;
    // lazy is synchronised)
    private val entries: Map<String, Entry> by lazy { load() }

    private fun load(): Map<String, Entry> = try {
        val json = context.assets.open(MANIFEST).bufferedReader().use { it.readText() }
        val root = JSONObject(json)
        root.keys().asSequence().associateWith { path ->
            val o = root.getJSONObject(path)
            Entry(
                o.getString("asset"),
                o.getString("v"),
                o.getLong("size"),
                o.optString("type", "application/json")
            )
        }
    } catch (e: Exception) {
        Log.w(TAG, "No offline data in this build", e)
        emptyMap()
    }

    fun intercept(request: WebResourceRequest): WebResourceResponse? {
        if (!request.method.equals("GET", ignoreCase = true)) return null
        val url = request.url
        if (url.scheme != "https" || url.host !in HOSTS) return null
        val entry = entries[url.path ?: return null] ?: return null
        if (url.getQueryParameter("v") != entry.version && isOnline()) return null

        return try {
            WebResourceResponse(
                entry.type,
                "utf-8",
                200,
                "OK",
                mapOf(
                    "Cache-Control" to "public, max-age=2592000, immutable",
                    "Content-Length" to entry.size.toString()
                ),
                context.assets.open(entry.asset)
            )
        } catch (e: Exception) {
            Log.w(TAG, "Could not open ${entry.asset}", e)
            null
        }
    }

    private fun isOnline(): Boolean {
        val cm = context.getSystemService(ConnectivityManager::class.java) ?: return true
        val caps = cm.getNetworkCapabilities(cm.activeNetwork) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
    }

    companion object {
        private const val TAG = "OfflineAssets"
        private const val MANIFEST = "offline/manifest.json"
        val HOSTS = setOf("oacapp.co.za", "www.oacapp.co.za")
    }
}
