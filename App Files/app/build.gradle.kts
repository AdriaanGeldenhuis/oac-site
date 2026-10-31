import java.security.MessageDigest

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.google.gms.google.services)
}

android {
    namespace = "za.co.oacapp.oacapp"
    compileSdk = 36

    defaultConfig {
        applicationId = "za.co.oacapp.oacapp"
        minSdk = 24
        targetSdk = 36
        versionCode = 4
        versionName = "1.3"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
    kotlinOptions {
        jvmTarget = "11"
    }
    buildFeatures {
        compose = false
    }
}

/**
 * The Bible and the hymn book travel inside the app, so nobody has to
 * download them first. At build time this copies them from the website's
 * own folders in this repo (bible/bibles, singemmanuel/songs) into the
 * app's assets, and writes offline/manifest.json:
 *
 *   { "/bible/bibles/af_1933_53.json": { "asset": "offline/bible/bibles/af_1933_53.json",
 *                                       "v": "<md5, 12 hex>", "size": 5308595, "type": "application/json" }, ... }
 *
 * "v" is computed exactly like oac_data_version() in includes/offline.php,
 * so OfflineAssets.kt knows when the server still has this same file.
 * Line endings are made LF, as on the server, in case a Windows checkout
 * turned them into CRLF.
 */
abstract class OfflineDataTask : DefaultTask() {
    @get:InputFiles
    @get:PathSensitive(PathSensitivity.RELATIVE)
    abstract val dataFiles: ConfigurableFileCollection

    @get:Internal
    abstract val webRoot: DirectoryProperty

    @get:OutputDirectory
    abstract val outputDir: DirectoryProperty

    @TaskAction
    fun copy() {
        val out = outputDir.get().asFile
        out.deleteRecursively()
        val root = webRoot.get().asFile
        val entries = dataFiles.files.sortedBy { it.path }.map { file ->
            val webPath = file.relativeTo(root).invariantSeparatorsPath
            val bytes = file.readText(Charsets.UTF_8).replace("\r\n", "\n").toByteArray(Charsets.UTF_8)
            val md5 = MessageDigest.getInstance("MD5").digest(bytes).joinToString("") { "%02x".format(it) }
            val asset = "offline/$webPath"
            File(out, asset).apply { parentFile.mkdirs(); writeBytes(bytes) }
            "  \"/$webPath\": {\"asset\": \"$asset\", \"v\": \"${md5.take(12)}\", " +
                "\"size\": ${bytes.size}, \"type\": \"application/json\"}"
        }
        File(out, "offline/manifest.json").writeText(entries.joinToString(",\n", "{\n", "\n}\n"))
    }
}

val offlineData = tasks.register<OfflineDataTask>("offlineData") {
    val web = rootDir.parentFile // the website: this repo's root, above "App Files"
    webRoot.set(web)
    dataFiles.from(
        File(web, "bible/bibles/af_1933_53.json"),
        File(web, "bible/bibles/en_kjv1611.json"),
        File(web, "singemmanuel/songs/sing_af.json"),
        File(web, "singemmanuel/songs/sing_en.json"),
    )
}

androidComponents {
    onVariants { variant ->
        variant.sources.assets?.addGeneratedSourceDirectory(offlineData, OfflineDataTask::outputDir)
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.activity.ktx)
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.messaging.ktx)
    implementation("androidx.webkit:webkit:1.11.0")
    implementation(libs.material)
    implementation("androidx.constraintlayout:constraintlayout:2.1.4")
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
}