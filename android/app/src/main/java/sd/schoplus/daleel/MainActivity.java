package sd.schoplus.daleel;

import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.ConnectivityManager;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.AccelerateInterpolator;
import android.view.animation.DecelerateInterpolator;
import android.webkit.CookieManager;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.ProgressBar;
import android.widget.Toast;
import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.RequiresApi;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.splashscreen.SplashScreen;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

public class MainActivity extends AppCompatActivity {

    private static final String APP_URL = "https://daleel.schoplus.sd/mobile/";

    private WebView webView;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ProgressBar topProgressBar;
    private View layoutOffline;
    private Button btnRetry;

    private View splashOverlay;
    private Handler splashHandler;
    private Runnable dismissRunnable;
    private boolean isSplashDismissed = false;

    private long lastBackPressedTime = 0;
    private Toast backToast;

    private ValueCallback<Uri[]> fileUploadCallback;
    private ActivityResultLauncher<Intent> fileChooserLauncher;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        SplashScreen.installSplashScreen(this);
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        initViews();
        setupFileChooser();
        setupWebView();
        setupBackNavigation();

        showAnimatedSplashScreen();

        if (isNetworkAvailable()) {
            webView.loadUrl(APP_URL);
        } else {
            showOfflineState(true);
        }
    }

    private void initViews() {
        webView = findViewById(R.id.webView);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
        topProgressBar = findViewById(R.id.topProgressBar);
        layoutOffline = findViewById(R.id.layoutOffline);
        btnRetry = findViewById(R.id.btnRetry);

        swipeRefreshLayout.setColorSchemeColors(0xFFF5A020, 0xFF0B2A52, 0xFF1E5FA8);
        swipeRefreshLayout.setOnRefreshListener(() -> {
            if (isNetworkAvailable()) {
                showOfflineState(false);
                webView.reload();
            } else {
                swipeRefreshLayout.setRefreshing(false);
                showOfflineState(true);
            }
        });

        btnRetry.setOnClickListener(v -> {
            if (isNetworkAvailable()) {
                showOfflineState(false);
                webView.loadUrl(APP_URL);
            } else {
                Toast.makeText(this, R.string.offline_title, Toast.LENGTH_SHORT).show();
            }
        });
    }

    private void setupFileChooser() {
        fileChooserLauncher = registerForActivityResult(
                new ActivityResultContracts.StartActivityForResult(),
                result -> {
                    if (fileUploadCallback == null) return;
                    Uri[] results = null;
                    if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                        if (result.getData().getClipData() != null) {
                            int count = result.getData().getClipData().getItemCount();
                            results = new Uri[count];
                            for (int i = 0; i < count; i++) {
                                results[i] = result.getData().getClipData().getItemAt(i).getUri();
                            }
                        } else if (result.getData().getData() != null) {
                            results = new Uri[]{result.getData().getData()};
                        }
                    }
                    fileUploadCallback.onReceiveValue(results);
                    fileUploadCallback = null;
                }
        );
    }

    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        }

        String defaultUa = settings.getUserAgentString();
        settings.setUserAgentString(defaultUa + " SchoPlusApp/3.5 SchoPlusDaleelMobile/1.0");

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (topProgressBar != null) {
                    if (newProgress < 100) {
                        topProgressBar.setVisibility(View.VISIBLE);
                        topProgressBar.setProgress(newProgress);
                    } else {
                        topProgressBar.setVisibility(View.GONE);
                        swipeRefreshLayout.setRefreshing(false);
                        dismissAnimatedSplashScreen();
                    }
                }
            }

            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (fileUploadCallback != null) {
                    fileUploadCallback.onReceiveValue(null);
                }
                fileUploadCallback = filePathCallback;

                Intent intent = fileChooserParams.createIntent();
                try {
                    fileChooserLauncher.launch(intent);
                } catch (ActivityNotFoundException e) {
                    fileUploadCallback = null;
                    Toast.makeText(MainActivity.this, "لا يمكن فتح مدير الملفات", Toast.LENGTH_SHORT).show();
                    return false;
                }
                return true;
            }
        });

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handleUrl(request.getUrl().toString());
            }

            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleUrl(url);
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                if (topProgressBar != null) {
                    topProgressBar.setVisibility(View.VISIBLE);
                }
                try {
                    CookieManager cookieManager = CookieManager.getInstance();
                    cookieManager.setAcceptCookie(true);
                    cookieManager.setCookie("https://daleel.schoplus.sd", "is_daleel_app=1; path=/");
                } catch (Exception ignored) {}
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                swipeRefreshLayout.setRefreshing(false);
                if (topProgressBar != null) {
                    topProgressBar.setVisibility(View.GONE);
                }
                dismissAnimatedSplashScreen();

                // Pure Mobile App Shell Isolation: Guarantee removal of web topbars and footers
                String hideScript = "javascript:(function() {" +
                        "  var style = document.getElementById('daleel-app-isolation-style');" +
                        "  if (!style) {" +
                        "    style = document.createElement('style');" +
                        "    style.id = 'daleel-app-isolation-style';" +
                        "    style.innerHTML = 'header.daleel-navbar, .daleel-topbar, .daleel-topbar-mobile, .daleel-footer, footer, #daleel-footer { display: none !important; visibility: hidden !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }';" +
                        "    document.head.appendChild(style);" +
                        "  }" +
                        "  document.body.classList.add('is-daleel-app');" +
                        "})();";
                view.evaluateJavascript(hideScript, null);
            }

            @RequiresApi(api = Build.VERSION_CODES.M)
            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                super.onReceivedError(view, request, error);
                if (request.isForMainFrame()) {
                    showOfflineState(true);
                }
            }

            @SuppressWarnings("deprecation")
            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                super.onReceivedError(view, errorCode, description, failingUrl);
                showOfflineState(true);
            }
        });
    }

    private boolean handleUrl(String url) {
        if (url == null) return false;

        // WhatsApp Direct Link
        if (url.startsWith("whatsapp:") || url.contains("api.whatsapp.com") || url.contains("wa.me")) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                // If whatsapp app is not installed, continue in web
                return false;
            }
        }

        // Phone call
        if (url.startsWith("tel:")) {
            try {
                Intent intent = new Intent(Intent.ACTION_DIAL, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                return true;
            }
        }

        // Email
        if (url.startsWith("mailto:")) {
            try {
                Intent intent = new Intent(Intent.ACTION_SENDTO, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                return true;
            }
        }

        // Maps
        if (url.startsWith("geo:") || url.contains("maps.google.com") || url.contains("goo.gl/maps")) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                return false;
            }
        }

        // Open External Sites outside daleel.schoplus.sd in browser
        if (!url.contains("schoplus.sd")) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                return false;
            }
        }

        // Keep inside Mobile App Shell: Map Web School Links to Mobile School View
        if (url.contains("daleel.schoplus.sd")) {
            Uri parsedUri = Uri.parse(url);
            String path = parsedUri.getPath();

            // Redirect web school profile to native mobile view
            if (path != null && (path.equals("/school.php") || path.startsWith("/school/"))) {
                String schoolId = parsedUri.getQueryParameter("id");
                String schoolSlug = parsedUri.getQueryParameter("slug");
                if (schoolId != null && !schoolId.isEmpty()) {
                    webView.loadUrl("https://daleel.schoplus.sd/mobile/?school_id=" + schoolId);
                    return true;
                } else if (schoolSlug != null && !schoolSlug.isEmpty()) {
                    webView.loadUrl("https://daleel.schoplus.sd/mobile/?slug=" + schoolSlug);
                    return true;
                } else if (path.startsWith("/school/")) {
                    String slugFromPath = path.substring("/school/".length()).trim();
                    if (!slugFromPath.isEmpty()) {
                        webView.loadUrl("https://daleel.schoplus.sd/mobile/?slug=" + slugFromPath);
                        return true;
                    }
                }
            } else if (path != null && path.equals("/login.php")) {
                webView.loadUrl("https://daleel.schoplus.sd/mobile/?tab=login");
                return true;
            } else if (path != null && path.equals("/register.php")) {
                webView.loadUrl("https://daleel.schoplus.sd/mobile/?tab=register");
                return true;
            } else if (path != null && (path.equals("/") || path.equals("/index.php"))) {
                // If user clicks home link inside web page, route to native mobile home
                if (!url.contains("/mobile/")) {
                    webView.loadUrl("https://daleel.schoplus.sd/mobile/");
                    return true;
                }
            }
        }

        return false;
    }

    private void setupBackNavigation() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (layoutOffline.getVisibility() == View.VISIBLE) {
                    finish();
                    return;
                }

                if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    if (System.currentTimeMillis() - lastBackPressedTime < 2000) {
                        if (backToast != null) {
                            backToast.cancel();
                        }
                        finish();
                    } else {
                        lastBackPressedTime = System.currentTimeMillis();
                        backToast = Toast.makeText(getApplicationContext(), R.string.press_again_to_exit, Toast.LENGTH_SHORT);
                        backToast.show();
                    }
                }
            }
        });
    }

    private void showAnimatedSplashScreen() {
        LayoutInflater inflater = LayoutInflater.from(this);
        splashOverlay = inflater.inflate(R.layout.splash_screen_overlay, null);
        addContentView(splashOverlay, new ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
        ));

        final View logo = splashOverlay.findViewById(R.id.splash_logo);
        final View progress = splashOverlay.findViewById(R.id.splash_progress);
        final View tagline = splashOverlay.findViewById(R.id.splash_tagline);
        final View subtag = splashOverlay.findViewById(R.id.splash_subtag);

        if (logo != null) {
            logo.setAlpha(0f);
            logo.setScaleX(0.88f);
            logo.setScaleY(0.88f);
            logo.setTranslationY(40f);

            if (progress != null) progress.setAlpha(0f);
            if (tagline != null) tagline.setAlpha(0f);
            if (subtag != null) subtag.setAlpha(0f);

            // Cinematic Decelerate Entry (550ms)
            logo.animate()
                    .alpha(1f)
                    .scaleX(1f)
                    .scaleY(1f)
                    .translationY(0f)
                    .setDuration(550)
                    .setInterpolator(new DecelerateInterpolator(1.8f))
                    .withEndAction(() -> {
                        if (progress != null) {
                            progress.animate().alpha(1f).setDuration(300).start();
                        }
                        if (tagline != null) {
                            tagline.animate().alpha(1f).setDuration(400).start();
                        }
                        if (subtag != null) {
                            subtag.animate().alpha(1f).setDuration(450).start();
                        }
                    })
                    .start();
        }

        // Automatic dismiss fallback after 2.5s if network is slow
        splashHandler = new Handler(Looper.getMainLooper());
        dismissRunnable = this::dismissAnimatedSplashScreen;
        splashHandler.postDelayed(dismissRunnable, 2500);
    }

    private void dismissAnimatedSplashScreen() {
        if (isSplashDismissed || splashOverlay == null || splashOverlay.getParent() == null) {
            return;
        }
        isSplashDismissed = true;

        if (splashHandler != null && dismissRunnable != null) {
            splashHandler.removeCallbacks(dismissRunnable);
        }

        final View logo = splashOverlay.findViewById(R.id.splash_logo);
        final View progress = splashOverlay.findViewById(R.id.splash_progress);
        final View tagline = splashOverlay.findViewById(R.id.splash_tagline);
        final View subtag = splashOverlay.findViewById(R.id.splash_subtag);

        if (logo != null) {
            logo.animate()
                    .scaleX(1.08f)
                    .scaleY(1.08f)
                    .alpha(0f)
                    .setDuration(300)
                    .setInterpolator(new AccelerateInterpolator())
                    .start();
        }
        if (progress != null) progress.animate().alpha(0f).setDuration(200).start();
        if (tagline != null) tagline.animate().alpha(0f).setDuration(200).start();
        if (subtag != null) subtag.animate().alpha(0f).setDuration(200).start();

        splashOverlay.animate()
                .alpha(0f)
                .setDuration(350)
                .withEndAction(() -> {
                    if (splashOverlay != null && splashOverlay.getParent() != null) {
                        ((ViewGroup) splashOverlay.getParent()).removeView(splashOverlay);
                    }
                })
                .start();
    }

    private void showOfflineState(boolean isOffline) {
        if (layoutOffline != null) {
            layoutOffline.setVisibility(isOffline ? View.VISIBLE : View.GONE);
        }
        if (swipeRefreshLayout != null) {
            swipeRefreshLayout.setVisibility(isOffline ? View.GONE : View.VISIBLE);
        }
        dismissAnimatedSplashScreen();
    }

    private boolean isNetworkAvailable() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return false;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            android.net.Network network = cm.getActiveNetwork();
            if (network == null) return false;
            NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
            return capabilities != null && (
                    capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) ||
                    capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) ||
                    capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET)
            );
        } else {
            android.net.NetworkInfo info = cm.getActiveNetworkInfo();
            return info != null && info.isConnected();
        }
    }

    @Override
    protected void onDestroy() {
        if (webView != null) {
            webView.destroy();
        }
        super.onDestroy();
    }
}
