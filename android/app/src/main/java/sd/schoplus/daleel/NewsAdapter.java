package sd.schoplus.daleel;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class NewsAdapter extends RecyclerView.Adapter<NewsAdapter.ViewHolder> {

    public interface OnNewsClickListener {
        void onNewsClick(NewsItem news);
    }

    private final Context context;
    private final List<NewsItem> list;
    private final OnNewsClickListener listener;

    public NewsAdapter(Context context, List<NewsItem> list, OnNewsClickListener listener) {
        this.context = context;
        this.list = list;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_news_card, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        NewsItem n = list.get(position);

        holder.tvNewsSchoolName.setText(n.schoolName);
        holder.tvNewsTitle.setText(n.title);
        holder.tvNewsSnippet.setText(n.content);
        holder.tvNewsDate.setText(n.publishedAt);

        holder.itemView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onNewsClick(n);
            }
        });
    }

    @Override
    public int getItemCount() {
        return list.size();
    }

    public static class ViewHolder extends RecyclerView.ViewHolder {
        ImageView imgNewsSchoolLogo;
        TextView tvNewsSchoolName, tvNewsDate, tvNewsTitle, tvNewsSnippet;

        public ViewHolder(@NonNull View itemView) {
            super(itemView);
            imgNewsSchoolLogo = itemView.findViewById(R.id.imgNewsSchoolLogo);
            tvNewsSchoolName = itemView.findViewById(R.id.tvNewsSchoolName);
            tvNewsDate = itemView.findViewById(R.id.tvNewsDate);
            tvNewsTitle = itemView.findViewById(R.id.tvNewsTitle);
            tvNewsSnippet = itemView.findViewById(R.id.tvNewsSnippet);
        }
    }
}
