using System;
using System.Collections.Generic;
using System.Text;

namespace QualityDocc.Domain.Entities
{
    public class Role : BaseEntity
    {
        // El Id ya no es necesario escribirlo aquí, se hereda de BaseEntity

        public string Name { get; set; } = string.Empty; // Ejemplo: Admin, Revisor, etc.
    }
}
