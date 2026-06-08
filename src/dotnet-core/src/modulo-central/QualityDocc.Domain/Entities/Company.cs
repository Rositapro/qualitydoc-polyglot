using System;
using System.Collections.Generic;
using System.Text;

namespace QualityDocc.Domain.Entities
{
    public class Company : BaseEntity
    {
        public string Name { get; set; } = string.Empty;
        public bool IsDeleted { get; set; } = false;
    }
}