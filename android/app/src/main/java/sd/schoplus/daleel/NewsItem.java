package sd.schoplus.daleel;

import org.json.JSONArray;
import org.json.JSONObject;
import java.io.Serializable;
import java.util.ArrayList;
import java.util.List;

public class NewsItem implements Serializable {
    public int id;
    public int schoolId;
    public String schoolName;
    public String schoolLogo;
    public String branchName;
    public String title;
    public String content;
    public String image;
    public String publishedAt;
    public int viewsCount;

    public static NewsItem fromJson(JSONObject obj) {
        NewsItem n = new NewsItem();
        n.id = obj.optInt("id", 0);
        n.schoolId = obj.optInt("school_id", 0);
        n.schoolName = obj.optString("school_name", "");
        n.schoolLogo = obj.optString("school_logo", "");
        n.branchName = obj.optString("branch_name", "");
        n.title = obj.optString("title", "");
        n.content = obj.optString("content", "");
        n.image = obj.optString("image", "");
        n.publishedAt = obj.optString("published_at", "");
        n.viewsCount = obj.optInt("views_count", 0);
        return n;
    }

    public static List<NewsItem> fromJsonArray(JSONArray arr) {
        List<NewsItem> list = new ArrayList<>();
        if (arr == null) return list;
        for (int i = 0; i < arr.length(); i++) {
            JSONObject obj = arr.optJSONObject(i);
            if (obj != null) {
                list.add(fromJson(obj));
            }
        }
        return list;
    }
}
