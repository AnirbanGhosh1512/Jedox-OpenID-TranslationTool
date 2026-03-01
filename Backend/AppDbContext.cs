using Microsoft.EntityFrameworkCore;
using TranslationApi.Models;

namespace TranslationApi.Data;

public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }

    public DbSet<Sid> Sids => Set<Sid>();
    public DbSet<Translation> Translations => Set<Translation>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<Sid>(e =>
        {
            e.ToTable("sids");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id");
            e.Property(x => x.SidKey).HasColumnName("sid").IsRequired();
            e.Property(x => x.DefaultText).HasColumnName("default_text").IsRequired();
            e.Property(x => x.CreatedAt).HasColumnName("created_at");
            e.Property(x => x.UpdatedAt).HasColumnName("updated_at");
            e.HasIndex(x => x.SidKey).IsUnique();
            e.HasMany(x => x.Translations)
             .WithOne(t => t.Sid)
             .HasForeignKey(t => t.SidId);
        });

        modelBuilder.Entity<Translation>(e =>
        {
            e.ToTable("translations");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id");
            e.Property(x => x.SidId).HasColumnName("sid_id");
            e.Property(x => x.LangId).HasColumnName("lang_id").IsRequired();
            e.Property(x => x.Text).HasColumnName("text").IsRequired();
            e.Property(x => x.CreatedAt).HasColumnName("created_at");
            e.Property(x => x.UpdatedAt).HasColumnName("updated_at");
            e.HasIndex(x => new { x.SidId, x.LangId }).IsUnique();
        });
    }
}