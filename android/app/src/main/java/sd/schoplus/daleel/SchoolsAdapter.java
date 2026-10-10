package sd.schoplus.daleel;

import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class SchoolsAdapter extends RecyclerView.Adapter<SchoolsAdapter.ViewHolder> {

    public interface OnSchoolClickListener {
        void onSchoolClick(School school);
    }

    private final Context context;
    private final List<School> list;
    private final OnSchoolClickListener listener;

    public SchoolsAdapter(Context context, List<School> list, OnSchoolClickListener listener) {
        this.context = context;
        this.list = list;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_school_card, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        School s = list.get(position);

        holder.tvSchoolName.setText(s.name);
        String loc = s.state;
        if (s.city != null && !s.city.isEmpty()) {
            loc += " — " + s.city;
        }
        holder.tvLocation.setText(loc);

        if (s.establishedYear != null && s.establishedYear > 0) {
            holder.tvEstablished.setVisibility(View.VISIBLE);
            holder.tvEstablished.setText("تأسست: " + s.establishedYear);
        } else {
            holder.tvEstablished.setVisibility(View.GONE);
        }

        if (s.isSchoplusClient) {
            holder.tvBadgeSchoplus.setVisibility(View.VISIBLE);
        } else {
            holder.tvBadgeSchoplus.setVisibility(View.GONE);
        }

        if (s.ratingAvg > 0) {
            holder.layoutRating.setVisibility(View.VISIBLE);
            holder.tvRating.setText(String.format(java.util.Locale.US, "%.1f", s.ratingAvg));
        } else {
            holder.layoutRating.setVisibility(View.GONE);
        }

        if (s.description != null && !s.description.isEmpty()) {
            holder.tvDescription.setVisibility(View.VISIBLE);
            holder.tvDescription.setText(s.description);
        } else {
            holder.tvDescription.setVisibility(View.GONE);
        }

        holder.tvTypeTag.setText(s.schoolType != null ? s.schoolType : "مدرسة خاصة");

        // Click Call
        holder.btnSchoolCall.setOnClickListener(v -> {
            if (s.phone != null && !s.phone.isEmpty()) {
                Intent intent = new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + s.phone));
                context.startActivity(intent);
            } else {
                Toast.makeText(context, "لا يتوفر رقم هاتف مسجل لهذه المدرسة", Toast.LENGTH_SHORT).show();
            }
        });

        // Click WhatsApp
        holder.btnSchoolWhatsapp.setOnClickListener(v -> {
            String waNum = (s.whatsapp != null && !s.whatsapp.isEmpty()) ? s.whatsapp : s.phone;
            if (waNum != null && !waNum.isEmpty()) {
                String cleanNum = waNum.replaceAll("[^0-9+]", "");
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/" + cleanNum));
                context.startActivity(intent);
            } else {
                Toast.makeText(context, "لا يتوفر رقم واتساب لهذه المدرسة", Toast.LENGTH_SHORT).show();
            }
        });

        // Card Click
        holder.itemView.setOnClickListener(v -> {
            if (listener != null) {
                listener.onSchoolClick(s);
            }
        });
    }

    @Override
    public int getItemCount() {
        return list.size();
    }

    public static class ViewHolder extends RecyclerView.ViewHolder {
        ImageView imgSchoolLogo;
        View btnSchoolWhatsapp, btnSchoolCall;
        TextView tvSchoolName, tvBadgeSchoplus, tvLocation, tvEstablished, tvRating, tvDescription, tvTypeTag;
        View layoutRating;

        public ViewHolder(@NonNull View itemView) {
            super(itemView);
            imgSchoolLogo = itemView.findViewById(R.id.imgSchoolLogo);
            btnSchoolWhatsapp = itemView.findViewById(R.id.btnSchoolWhatsapp);
            btnSchoolCall = itemView.findViewById(R.id.btnSchoolCall);
            tvSchoolName = itemView.findViewById(R.id.tvSchoolName);
            tvBadgeSchoplus = itemView.findViewById(R.id.tvBadgeSchoplus);
            tvLocation = itemView.findViewById(R.id.tvLocation);
            tvEstablished = itemView.findViewById(R.id.tvEstablished);
            tvRating = itemView.findViewById(R.id.tvRating);
            tvDescription = itemView.findViewById(R.id.tvDescription);
            tvTypeTag = itemView.findViewById(R.id.tvTypeTag);
            layoutRating = itemView.findViewById(R.id.layoutRating);
        }
    }
}
