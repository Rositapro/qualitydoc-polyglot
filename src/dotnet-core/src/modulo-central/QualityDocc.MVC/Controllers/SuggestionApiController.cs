using Microsoft.AspNetCore.Mvc;
using QualityDocc.Infrastructure.Data;
using QualityDocc.Domain.Entities;
using System.Threading.Tasks;
using System;

namespace QualityDocc.MVC.Controllers
{
    [Route("api/suggestions")]
    [ApiController]
    public class SuggestionApiController : ControllerBase
    {
        private readonly ApplicationDbContext _context;

        public SuggestionApiController(ApplicationDbContext context)
        {
            _context = context;
        }

        public class SuggestionRequest
        {
            public int DocumentId { get; set; }
            public string AuthorName { get; set; } = string.Empty;
            public string Comment { get; set; } = string.Empty;
            public int CompanyId { get; set; }
        }

        [HttpPost]
        public async Task<IActionResult> CreateSuggestion([FromBody] SuggestionRequest request)
        {
            if (request == null || string.IsNullOrEmpty(request.AuthorName) || string.IsNullOrEmpty(request.Comment) || request.DocumentId <= 0)
            {
                return BadRequest(new { success = false, message = "Datos de sugerencia inválidos o incompletos." });
            }

            // Validar que el documento exista
            var docExists = await _context.Document.FindAsync(request.DocumentId);
            if (docExists == null)
            {
                return NotFound(new { success = false, message = $"El documento con ID {request.DocumentId} no existe." });
            }

            var newSuggestion = new Suggestion
            {
                DocumentId = request.DocumentId,
                AuthorName = request.AuthorName,
                Comment = request.Comment,
                CompanyId = request.CompanyId,
                DateCreate = DateTime.Now,
                Status = true
            };

            _context.Suggestions.Add(newSuggestion);
            await _context.SaveChangesAsync();

            return Ok(new { success = true, message = "Sugerencia registrada con éxito." });
        }
    }
}
