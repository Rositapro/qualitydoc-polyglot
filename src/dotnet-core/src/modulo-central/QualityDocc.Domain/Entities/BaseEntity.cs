using System;
using System.ComponentModel.DataAnnotations; // Necesario
using System.ComponentModel.DataAnnotations.Schema; // Necesario

namespace QualityDocc.Domain.Entities
{
    public abstract class BaseEntity
    {
        [Key] // Marca este campo como Llave Primaria
        [DatabaseGenerated(DatabaseGeneratedOption.Identity)] // Indica que SQL Server asigna el valor automáticamente
        public int Id { get; set; }

        public int? IdUserCreate { get; set; }
        public DateTime? DateCreate { get; set; } = DateTime.Now;
        public int? IdUserUpdate { get; set; }
        public DateTime? DateUpdate { get; set; }
        public int? IdUserDelete { get; set; }
        public DateTime? DateDelete { get; set; }
        public bool Status { get; set; } = true;
    }
}