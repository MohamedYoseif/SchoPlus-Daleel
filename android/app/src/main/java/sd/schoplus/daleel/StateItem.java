package sd.schoplus.daleel;

import org.json.JSONArray;
import org.json.JSONObject;
import java.io.Serializable;
import java.util.ArrayList;
import java.util.List;

public class StateItem implements Serializable {
    public String name;
    public int count;

    public StateItem(String name, int count) {
        this.name = name;
        this.count = count;
    }

    public static StateItem fromJson(JSONObject obj) {
        return new StateItem(
            obj.optString("name", ""),
            obj.optInt("count", 0)
        );
    }

    public static List<StateItem> fromJsonArray(JSONArray arr) {
        List<StateItem> list = new ArrayList<>();
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
