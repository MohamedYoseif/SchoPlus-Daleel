package sd.schoplus.daleel;

import org.json.JSONArray;
import org.json.JSONObject;
import java.io.Serializable;
import java.util.ArrayList;
import java.util.List;

public class School implements Serializable {
    public int id;
    public String name;
    public String nameEn;
    public String slug;
    public String schoolType;
    public String genderType;
    public String curriculum;
    public Integer establishedYear;
    public String state;
    public String city;
    public String district;
    public String address;
    public String phone;
    public String whatsapp;
    public String email;
    public String website;
    public String logo;
    public String description;
    public boolean isSchoplusClient;
    public boolean isVerified;
    public boolean isFeatured;
    public double ratingAvg;
    public int reviewsCount;
    public int directoryRank;
    public String shareUrl;

    public static School fromJson(JSONObject obj) {
        School s = new School();
        s.id = obj.optInt("id", 0);
        s.name = obj.optString("name", "");
        s.nameEn = obj.optString("name_en", "");
        s.slug = obj.optString("slug", "");
        s.schoolType = obj.optString("school_type", "خاصة");
        s.genderType = obj.optString("gender_type", "مختلط");
        s.curriculum = obj.optString("curriculum", "سوداني");
        if (obj.has("established_year") && !obj.isNull("established_year")) {
            s.establishedYear = obj.optInt("established_year");
        }
        s.state = obj.optString("state", "");
        s.city = obj.optString("city", "");
        s.district = obj.optString("district", "");
        s.address = obj.optString("address", "");
        s.phone = obj.optString("phone", "");
        s.whatsapp = obj.optString("whatsapp", "");
        s.email = obj.optString("email", "");
        s.website = obj.optString("website", "");
        s.logo = obj.optString("logo", "");
        s.description = obj.optString("description", "");
        s.isSchoplusClient = obj.optBoolean("is_schoplus_client", false);
        s.isVerified = obj.optBoolean("is_verified", false);
        s.isFeatured = obj.optBoolean("is_featured", false);
        s.ratingAvg = obj.optDouble("rating_avg", 0.0);
        s.reviewsCount = obj.optInt("reviews_count", 0);
        s.directoryRank = obj.optInt("directory_rank", 1);
        s.shareUrl = obj.optString("share_url", "https://daleel.schoplus.sd/school.php?id=" + s.id);
        return s;
    }

    public static List<School> fromJsonArray(JSONArray arr) {
        List<School> list = new ArrayList<>();
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
