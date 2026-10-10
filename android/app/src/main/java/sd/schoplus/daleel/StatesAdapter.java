package sd.schoplus.daleel;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class StatesAdapter extends RecyclerView.Adapter<StatesAdapter.ViewHolder> {

    public interface OnStateClickListener {
        void onStateClick(StateItem item);
    }

    private final Context context;
    private final List<StateItem> list;
    private final OnStateClickListener listener;

    public StatesAdapter(Context context, List<StateItem> list, OnStateClickListener listener) {
        this.context = context;
        this.list = list;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_state_card, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        StateItem s = list.get(position);
        holder.tvStateName.setText("ولاية " + s.name);
        holder.tvStateCount.setText(s.count + " مدارس");

        holder.itemView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onStateClick(s);
            }
        });
    }

    @Override
    public int getItemCount() {
        return list.size();
    }

    public static class ViewHolder extends RecyclerView.ViewHolder {
        TextView tvStateName, tvStateCount;

        public ViewHolder(@NonNull View itemView) {
            super(itemView);
            tvStateName = itemView.findViewById(R.id.tvStateName);
            tvStateCount = itemView.findViewById(R.id.tvStateCount);
        }
    }
}
