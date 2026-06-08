using System;
using System.IO;
using System.Text;
using UglyToad.PdfPig;

namespace QualityDocc.Infrastructure.Utilities
{
    public static class PdfParser
    {
        public static string ExtractText(string filePath)
        {
            if (string.IsNullOrEmpty(filePath) || !File.Exists(filePath))
            {
                return string.Empty;
            }

            try
            {
                var text = new StringBuilder();
                using (var pdf = PdfDocument.Open(filePath))
                {
                    foreach (var page in pdf.GetPages())
                    {
                        text.AppendLine(page.Text);
                    }
                }
                return text.ToString();
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error al extraer texto del PDF {filePath}: {ex.Message}");
                return string.Empty;
            }
        }
    }
}
