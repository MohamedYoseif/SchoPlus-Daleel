package sd.schoplus.daleel;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.MenuItem;
import android.view.View;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.splashscreen.SplashScreen;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.google.android.material.bottomnavigation.BottomNavigationView;
import org.json.JSONObject;
import java.net.URLEncoder;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends AppCompatActivity {

    private SwipeRefreshLayout swipeRefreshLayout;
    private RecyclerView rvSchools, rvStates, rvNews;
    private SchoolsAdapter schoolsAdapter;
    private StatesAdapter statesAdapter;
    private NewsAdapter newsAdapter;

    private final List<School> schoolList = new ArrayList<>();
    private final List<StateItem> stateList = new ArrayList<>();
    private final List<NewsItem> newsList = new ArrayList<>();

    private EditText etSearch;
    private ImageView btnClearSearch, btnRefreshTop;
    private ProgressBar pbLoading;
    private TextView tvEmptyState, tvSchoolsCount, tvSchoolsSectionTitle;
    private LinearLayout sectionStates, layoutNewsFeed;
    private BottomNavigationView bottomNav;

    private String currentStateFilter = "";
    private boolean isSchoplusFilter = false;
    private long backPressedTime = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        SplashScreen.installSplashScreen(this);
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        initViews();
        setupAdapters();
        setupListeners();

        loadHomeData();
    }

    private void initViews() {
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
        swipeRefreshLayout.setColorSchemeColors(getColor(R.color.colorGold), getColor(R.color.colorBlue));

        rvSchools = findViewById(R.id.rvSchools);
        rvStates = findViewById(R.id.rvStates);
        rvNews = findViewById(R.id.rvNews);

        etSearch = findViewById(R.id.etSearchSchool);
        btnClearSearch = findViewById(R.id.btnClearSearch);
        btnRefreshTop = findViewById(R.id.btnRefreshTop);
        pbLoading = findViewById(R.id.pbLoading);
        tvEmptyState = findViewById(R.id.tvEmptyState);
        tvSchoolsCount = findViewById(R.id.tvSchoolsCount);
        tvSchoolsSectionTitle = findViewById(R.id.tvSchoolsSectionTitle);

        sectionStates = findViewById(R.id.sectionStates);
        layoutNewsFeed = findViewById(R.id.layoutNewsFeed);
        bottomNav = findViewById(R.id.bottomNavigationView);
    }

    private void setupAdapters() {
        // Schools Vertical List
        schoolsAdapter = new SchoolsAdapter(this, schoolList, school -> {
            Intent intent = new Intent(MainActivity.this, SchoolDetailActivity.class);
            intent.putExtra("school", school);
            startActivity(intent);
        });
        rvSchools.setLayoutManager(new LinearLayoutManager(this));
        rvSchools.setAdapter(schoolsAdapter);

        // States Horizontal List
        statesAdapter = new StatesAdapter(this, stateList, stateItem -> {
            currentStateFilter = stateItem.name;
            etSearch.setText("");
            performSearch();
        });
        rvStates.setLayoutManager(new LinearLayoutManager(this, LinearLayoutManager.HORIZONTAL, false));
        rvStates.setAdapter(statesAdapter);

        // News List
        newsAdapter = new NewsAdapter(this, newsList, news -> {
            if (news.schoolId > 0) {
                School dummy = new School();
                dummy.id = news.schoolId;
                dummy.name = news.schoolName;
                dummy.shareUrl = "https://daleel.schoplus.sd/school.php?id=" + news.schoolId;
                Intent intent = new Intent(MainActivity.this, SchoolDetailActivity.class);
                intent.putExtra("school", dummy);
                startActivity(intent);
            }
        });
        rvNews.setLayoutManager(new LinearLayoutManager(this));
        rvNews.setAdapter(newsAdapter);
    }

    private void setupListeners() {
        swipeRefreshLayout.setOnRefreshListener(() -> {
            if (layoutNewsFeed.getVisibility() == View.VISIBLE) {
                loadNewsData();
            } else if (!currentStateFilter.isEmpty() || etSearch.getText().length() > 0 || isSchoplusFilter) {
                performSearch();
            } else {
                loadHomeData();
            }
        });

        btnRefreshTop.setOnClickListener(v -> {
            currentStateFilter = "";
            isSchoplusFilter = false;
            etSearch.setText("");
            loadHomeData();
        });

        btnClearSearch.setOnClickListener(v -> {
            etSearch.setText("");
            currentStateFilter = "";
            isSchoplusFilter = false;
            loadHomeData();
        });

        etSearch.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                btnClearSearch.setVisibility(s.length() > 0 ? View.VISIBLE : View.GONE);
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (s.length() >= 2) {
                    performSearch();
                } else if (s.length() == 0) {
                    loadHomeData();
                }
            }
        });

        bottomNav.setOnItemSelectedListener(new BottomNavigationView.OnNavigationItemSelectedListener() {
            @Override
            public boolean onNavigationItemSelected(@NonNull MenuItem item) {
                int itemId = item.getItemId();
                if (itemId == R.id.nav_home) {
                    showHomeView();
                    currentStateFilter = "";
                    isSchoplusFilter = false;
                    etSearch.setText("");
                    loadHomeData();
                    return true;
                } else if (itemId == R.id.nav_search) {
                    showHomeView();
                    etSearch.requestFocus();
                    return true;
                } else if (itemId == R.id.nav_news) {
                    showNewsView();
                    loadNewsData();
                    return true;
                } else if (itemId == R.id.nav_schoplus) {
                    showHomeView();
                    isSchoplusFilter = true;
                    currentStateFilter = "";
                    performSearch();
                    return true;
                }
                return false;
            }
        });
    }

    private void showHomeView() {
        layoutNewsFeed.setVisibility(View.GONE);
        findViewById(R.id.nestedScrollView).setVisibility(View.VISIBLE);
    }

    private void showNewsView() {
        findViewById(R.id.nestedScrollView).setVisibility(View.GONE);
        layoutNewsFeed.setVisibility(View.VISIBLE);
    }

    private void loadHomeData() {
        pbLoading.setVisibility(View.VISIBLE);
        sectionStates.setVisibility(View.VISIBLE);
        tvSchoolsSectionTitle.setText("🏫 المدارس المعتمدة في الدليل");

        ApiClient.get("?action=home", new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(String response) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);

                try {
                    JSONObject json = new JSONObject(response);
                    if (json.optBoolean("success")) {
                        JSONObject data = json.optJSONObject("data");
                        if (data != null) {
                            // States
                            stateList.clear();
                            stateList.addAll(StateItem.fromJsonArray(data.optJSONArray("states")));
                            statesAdapter.notifyDataSetChanged();

                            // Schools (Combined Latest + Top Rated)
                            schoolList.clear();
                            schoolList.addAll(School.fromJsonArray(data.optJSONArray("schoplus_schools")));
                            schoolList.addAll(School.fromJsonArray(data.optJSONArray("top_rated_schools")));
                            schoolsAdapter.notifyDataSetChanged();

                            tvSchoolsCount.setText(schoolList.size() + " مدارس");
                            tvEmptyState.setVisibility(schoolList.isEmpty() ? View.VISIBLE : View.GONE);
                        }
                    }
                } catch (Exception e) {
                    // Fail silently or log
                }
            }

            @Override
            public void onError(Exception e) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
            }
        });
    }

    private void performSearch() {
        pbLoading.setVisibility(View.VISIBLE);
        String query = etSearch.getText().toString().trim();

        StringBuilder params = new StringBuilder("?action=schools&limit=30");
        try {
            if (!query.isEmpty()) {
                params.append("&q=").append(URLEncoder.encode(query, "UTF-8"));
            }
            if (!currentStateFilter.isEmpty()) {
                params.append("&state=").append(URLEncoder.encode(currentStateFilter, "UTF-8"));
            }
            if (isSchoplusFilter) {
                params.append("&schoplus_only=1");
            }
        } catch (Exception ignored) {}

        if (!currentStateFilter.isEmpty()) {
            tvSchoolsSectionTitle.setText("📍 مدارس ولاية " + currentStateFilter);
        } else if (isSchoplusFilter) {
            tvSchoolsSectionTitle.setText("⚡ مدارس SchoPlus الذكية");
        } else if (!query.isEmpty()) {
            tvSchoolsSectionTitle.setText("🔍 نتائج البحث عن: " + query);
        }

        ApiClient.get(params.toString(), new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(String response) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);

                try {
                    JSONObject json = new JSONObject(response);
                    if (json.optBoolean("success")) {
                        JSONObject data = json.optJSONObject("data");
                        if (data != null) {
                            schoolList.clear();
                            schoolList.addAll(School.fromJsonArray(data.optJSONArray("schools")));
                            schoolsAdapter.notifyDataSetChanged();

                            tvSchoolsCount.setText(schoolList.size() + " مدارس");
                            tvEmptyState.setVisibility(schoolList.isEmpty() ? View.VISIBLE : View.GONE);
                        }
                    }
                } catch (Exception ignored) {
                }
            }

            @Override
            public void onError(Exception e) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
            }
        });
    }

    private void loadNewsData() {
        pbLoading.setVisibility(View.VISIBLE);
        ApiClient.get("?action=news&limit=20", new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(String response) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);

                try {
                    JSONObject json = new JSONObject(response);
                    if (json.optBoolean("success")) {
                        JSONObject data = json.optJSONObject("data");
                        if (data != null) {
                            newsList.clear();
                            newsList.addAll(NewsItem.fromJsonArray(data.optJSONArray("news")));
                            newsAdapter.notifyDataSetChanged();
                        }
                    }
                } catch (Exception ignored) {}
            }

            @Override
            public void onError(Exception e) {
                pbLoading.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
            }
        });
    }

    @Override
    public void onBackPressed() {
        if (layoutNewsFeed.getVisibility() == View.VISIBLE || isSchoplusFilter || !currentStateFilter.isEmpty() || etSearch.getText().length() > 0) {
            showHomeView();
            currentStateFilter = "";
            isSchoplusFilter = false;
            etSearch.setText("");
            loadHomeData();
            return;
        }

        if (backPressedTime + 2000 > System.currentTimeMillis()) {
            super.onBackPressed();
        } else {
            Toast.makeText(this, "اضغط مرة أخرى للخروج من التطبيق", Toast.LENGTH_SHORT).show();
            backPressedTime = System.currentTimeMillis();
        }
    }
}
