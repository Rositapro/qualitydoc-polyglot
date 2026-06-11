using System;
using System.IO;
using DocumentFormat.OpenXml.Packaging;

namespace QualityDocc.Infrastructure.Utilities
{
    public static class DocxParser
    {
        public static string ExtractText(string filePath)
        {
            if (string.IsNullOrEmpty(filePath) || !File.Exists(filePath))
            {
                return string.Empty;
            }

            try
            {
                using (WordprocessingDocument wordDoc = WordprocessingDocument.Open(filePath, false))
                {
                    var body = wordDoc.MainDocumentPart?.Document?.Body;
                    return body != null ? body.InnerText : string.Empty;
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error al extraer texto del Word {filePath}: {ex.Message}");
                return string.Empty;
            }
        }
    }
}
