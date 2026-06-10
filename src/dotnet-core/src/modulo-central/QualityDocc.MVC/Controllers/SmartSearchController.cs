using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Application.Interfaces;
using QualityDocc.Infrastructure.Data;
using System.Collections.Generic;
using System.Security.Claims;
using System.Text.Json;
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
            // Diccionario: documentId => lista de versiones obsoletas (JSON)
            var obsoleteMap = new Dictionary<int, List<JsonNode>>();

            foreach (var json in rawResults)
            {
                try
                {
                    var node = JsonNode.Parse(json);
                    if (node != null)
                    {
                        parsedDocuments.Add(node);

                        // Para cada documento vigente, obtener su historial obsoleto
                        var docIdNode = node["metadata"]?["documentId"];
                        if (docIdNode != null && int.TryParse(docIdNode.ToString(), out int docId))
                        {
                            var obsoleteRaw = await _mongoService.GetObsoleteVersionsAsync(docId, currentUser.CompanyId ?? 1);
                            var obsoleteNodes = new List<JsonNode>();
                            foreach (var obsJson in obsoleteRaw)
                            {
                                try { var obsNode = JsonNode.Parse(obsJson); if (obsNode != null) obsoleteNodes.Add(obsNode); }
                                catch { }
                            }
                            obsoleteMap[docId] = obsoleteNodes;
                        }
                    }
                }
                catch (Exception ex)
                {
                    Console.WriteLine($"SmartSearchController PARSE ERROR: {ex.Message} | JSON: {json}");
                }
            }

            // Serializar como JSON simple: { "docId": [{...}, ...], ... }
            // Usamos string como clave para que la serialización sea válida
            var obsoleteMapStr = new Dictionary<string, List<string>>();
            foreach (var kv in obsoleteMap)
            {
                var jsonList = new List<string>();
                foreach (var node in kv.Value)
                    jsonList.Add(node.ToJsonString());
                obsoleteMapStr[kv.Key.ToString()] = jsonList;
            }
            ViewBag.ObsoleteVersionsJson = JsonSerializer.Serialize(obsoleteMapStr);
            return View(parsedDocuments);
        }
    }
}
