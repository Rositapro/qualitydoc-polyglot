using System;
using System.Collections.Generic;
using System.Text;

namespace QualityDocc.Domain.Entities
{
    public class Company : BaseEntity
    {
        public string Name { get; set; } = string.Empty;
        // Status (heredado de BaseEntity): true = Activa/Vigente | false = Eliminada/Inactiva
    }
}