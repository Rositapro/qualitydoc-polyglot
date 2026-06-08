using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Application.Interfaces;
using QualityDocc.Infrastructure.Data;
using System.Collections.Generic;
using System.Security.Claims;
using System.Text.Json.Nodes;
using System.Threading.Tasks;

namespace QualityDocc.MVC.Controllers
{
    [Authorize]
    public class SmartSearchController : Controller
    {
        private readonly IMongoDocumentService _mongoService;
        private readonly ApplicationDbContext _context;

        public SmartSearchController(IMongoDocumentService mongoService, ApplicationDbContext context)
        {
            _mongoService = mongoService;
            _context = context;
        }

        [HttpGet]
        public async Task<IActionResult> Index(string q)
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            ViewBag.SearchQuery = q;

            var rawResults = await _mongoService.SearchDocumentsAsync(q, currentUser.CompanyId ?? 1);
            var parsedDocuments = new List<JsonNode>();

            foreach (var json in rawResults)
            {
                try
                {
                    var node = JsonNode.Parse(json);
                    if (node != null)
                    {
                        parsedDocuments.Add(node);
                    }
                }
                catch
                {
                    // Ignorar errores individuales de parseo
                }
            }

            return View(parsedDocuments);
        }
    }
}
