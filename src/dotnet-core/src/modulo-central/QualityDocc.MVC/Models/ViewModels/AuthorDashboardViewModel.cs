using QualityDocc.MVC.Models; // Para que reconozca tu clase Document
using System.Collections.Generic;
using QualityDocc.Domain.Entities;
namespace QualityDocc.MVC.Models.ViewModels
{
    public class AuthorDashboardViewModel
    {
        public int TotalBorradores { get; set; }
        public int TotalAprobados { get; set; }
        public int TotalDevueltos { get; set; }
        public List<Document> UltimosBorradores { get; set; } = new List<Document>();
    }
}