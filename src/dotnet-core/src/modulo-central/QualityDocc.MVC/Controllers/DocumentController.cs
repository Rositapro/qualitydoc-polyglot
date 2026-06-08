using Microsoft.AspNetCore.Authorization; // <--- 1. ESTO ES OBLIGATORIO
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using QualityDocc.Application.Interfaces;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using System.IO;
using System.Security.Claims;
using QualityDocc.Domain.Entities; // Esto es lo que permite que el controlador conozca DocumentStatus

namespace QualityDocc.MVC.Controllers
{
    // Opcional: Si quieres que NADIE entre al sistema sin estar logueado, 
    // puedes poner [Authorize] aquí arriba de la clase.
    public class DocumentController : Controller
    {
        private readonly ApplicationDbContext _context;
        private readonly IDocumentService _documentService;
        private readonly IWebHostEnvironment _webHostEnvironment;

        public DocumentController(ApplicationDbContext context,
                                  IDocumentService documentService,
                                  IWebHostEnvironment webHostEnvironment)
        {
            _context = context;
            _documentService = documentService;
            _webHostEnvironment = webHostEnvironment;
        }

        public IActionResult Index()
        {
            return View();
        }

        // 2. PROTECCIÓN PARA CREAR (Solo Autores y Admins)
        [Authorize(Roles = "Author,SuperAdmin,Administrator")]
        [HttpGet]
        public IActionResult Create()
        {
            return View();
        }

        [Authorize(Roles = "Author,SuperAdmin,Administrator")]
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> Create(string title, IFormFile file)
        {
            // ... tu lógica de Create existente ...
            return RedirectToAction("Index");
        }

        // 3. AQUÍ VA LA PROTECCIÓN PARA APROBAR (Solo Aprobadores y Admins)
        [Authorize(Roles = "Reviewer,SuperAdmin,Administrator")]
        [HttpPost]
        public async Task<IActionResult> Approve(int documentId, string approvalNotes)
        {
            // Aquí llamarías a tu servicio para aprobar:
            // await _documentService.ApproveDocumentAsync(documentId, approvalNotes);

            return RedirectToAction("Index");
        }

        // ... dentro de tu DocumentController.cs

        // 1. Acción para enviar a revisión (Solo Autores)
        [Authorize(Roles = "Author,SuperAdmin,Administrator")]
        [HttpPost]
        public async Task<IActionResult> RequestReview(int id)
        {
            // Llamas a tu servicio para cambiar el estado a Revision
            await _documentService.UpdateStatusAsync(id, DocumentStatus.Revision);
            return RedirectToAction("Index");
        }

        // 2. Acción para rechazar (Solo Aprobadores)
        [Authorize(Roles = "Reviewer,SuperAdmin,Administrator")]
        [HttpPost]
        public async Task<IActionResult> Reject(int id, string reason)
        {
            if (string.IsNullOrWhiteSpace(reason))
            {
                ModelState.AddModelError("", "Debes indicar el motivo del rechazo.");
                return RedirectToAction("Index"); // O mostrar un error
            }

            // Llamas a tu servicio para cambiar el estado a Rechazado y guardar la razón
            await _documentService.RejectDocumentAsync(id, reason);
            return RedirectToAction("Index");
        }

    }


}