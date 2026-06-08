using Microsoft.AspNetCore.Mvc;
using QualityDocc.Application.Interfaces;
using QualityDocc.Domain.Entities;
using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using QualityDocc.Application.DTOs;

namespace QualityDocc.MVC.Controllers
{
    public class HomeController : Controller
    {
        private readonly IDocumentService _documentService;

        // Inyectamos el servicio core de control de versiones
        public HomeController(IDocumentService documentService)
        {
            _documentService = documentService;
        }

        // Acción del Panel Principal de Control
        // Acción del Panel Principal de Control (CONECTADO A LA BASE DE DATOS)
        public async Task<IActionResult> Index()
        {
            // Cambiamos la lista hardcoded por la llamada real al servicio.
            // Esto buscará en tu SQL lo que realmente existe.
            var documentos = await _documentService.GetAllDocumentsAsync();

            // Enviamos los datos reales a la vista
            return View(documentos);
        }

        // ==========================================
        // ACCIÓN PARA EL BOTÓN v++ (Subir Borrador Minor)
        // ==========================================
        [HttpPost]
        public async Task<IActionResult> IncrementVersion(int documentId, string changeLog)
        {
            try
            {
                // ID de usuario simulado (reemplazar por el ID real del usuario logueado después)
                int mockUserId = 1;

                // Ejecutamos la matemática del backend que creaste en el servicio
                var newVersion = await _documentService.IncrementMinorVersionAsync(documentId, changeLog, mockUserId);

                // Mensaje temporal de éxito para mostrar en la interfaz
                TempData["SuccessMessage"] = $"¡Borrador incrementado con éxito! Se registró la versión interna {newVersion.VersionNumber}.";
            }
            catch (Exception ex)
            {
                // Captura si el log venía vacío o si ya estaba aprobado
                TempData["ErrorMessage"] = ex.Message;
            }

            return RedirectToAction("Index");
        }

        // ==========================================
        // ACCIÓN PARA EL BOTÓN APROBAR (Fijar en v1.0)
        // ==========================================
        [HttpPost]
        public async Task<IActionResult> ApproveDocument(int documentId, string approvalNotes)
        {
            try
            {
                int mockUserId = 1;

                // Ejecutamos la lógica de aprobación normativa con notas obligatorias
                var approvedVersion = await _documentService.ApproveDocumentAsync(documentId, approvalNotes, mockUserId);

                TempData["SuccessMessage"] = "¡Documento aprobado normativamente!";
            }
            catch (Exception ex)
            {
                // Captura si las notas obligatorias venían vacías
                TempData["ErrorMessage"] = ex.Message;
            }

            return RedirectToAction("Index");
        }
    }
}