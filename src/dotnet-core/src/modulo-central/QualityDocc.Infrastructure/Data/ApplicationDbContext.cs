using Microsoft.EntityFrameworkCore;
using QualityDocc.Domain.Entities;
using System.Data;
using static Microsoft.EntityFrameworkCore.DbLoggerCategory.Database;

namespace QualityDocc.Infrastructure.Data
{
    public class ApplicationDbContext : DbContext
    {
        public ApplicationDbContext(DbContextOptions<ApplicationDbContext> options)
            : base(options)
        {
        }

        // Mapeo de tus entidades a conjuntos de datos (Tablas)
        public DbSet<Document> Document { get; set; } // ¡Faltaba registrar la tabla maestra!
        public DbSet<DocumentVersion> DocumentVersion { get; set; }
        public DbSet<ApprovalFlow> ApprovalFlow { get; set; }
        public DbSet<User> User { get; set; }
        public DbSet<Company> Company { get; set; }
        public DbSet<Role> Role { get; set; }
        public DbSet<Iso> Iso { get; set; }
        public DbSet<Suggestion> Suggestions { get; set; }

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            // Mapeo exacto a los nombres de tus tablas en SQL Server
            modelBuilder.Entity<Document>().ToTable("Document");
            modelBuilder.Entity<DocumentVersion>().ToTable("DocumentVersion");
            modelBuilder.Entity<ApprovalFlow>().ToTable("ApprovalFlow");
            modelBuilder.Entity<User>().ToTable("User"); // Mapeo a la tabla [User]
            modelBuilder.Entity<Company>().ToTable("Company");
            modelBuilder.Entity<Role>().ToTable("Role");
            modelBuilder.Entity<Iso>().ToTable("Iso");
            modelBuilder.Entity<Suggestion>().ToTable("Suggestion");


            // Configurar relación: Un Documento tiene muchas Versiones
            modelBuilder.Entity<QualityDocc.Domain.Entities.DocumentVersion>(entity =>
            {
                entity.HasOne<QualityDocc.Domain.Entities.Document>(v => v.Document)
                      .WithMany(d => d.Versions)
                      .HasForeignKey(v => v.DocumentId)
                      .OnDelete(Microsoft.EntityFrameworkCore.DeleteBehavior.Cascade);
            });

            modelBuilder.Entity<Document>()
                .Property(d => d.Status)
                .HasColumnType("bit")
                .HasDefaultValue(true); // Tu regla de bit NOT NULL default 1
        }
    }
}