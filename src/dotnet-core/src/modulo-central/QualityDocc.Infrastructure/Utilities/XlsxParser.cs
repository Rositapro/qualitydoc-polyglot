using System;
using System.IO;
using System.Linq;
using System.Text;
using DocumentFormat.OpenXml.Packaging;
using DocumentFormat.OpenXml.Spreadsheet;

namespace QualityDocc.Infrastructure.Utilities
{
    public static class XlsxParser
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
                using (SpreadsheetDocument spreadsheetDoc = SpreadsheetDocument.Open(filePath, false))
                {
                    WorkbookPart workbookPart = spreadsheetDoc.WorkbookPart;
                    if (workbookPart == null) return string.Empty;

                    SharedStringTablePart sharedStringTablePart = workbookPart.SharedStringTablePart;
                    SharedStringTable sharedStringTable = sharedStringTablePart?.SharedStringTable;

                    foreach (WorksheetPart worksheetPart in workbookPart.WorksheetParts)
                    {
                        Worksheet worksheet = worksheetPart.Worksheet;
                        SheetData sheetData = worksheet.GetFirstChild<SheetData>();
                        if (sheetData == null) continue;

                        foreach (Row row in sheetData.Elements<Row>())
                        {
                            foreach (Cell cell in row.Elements<Cell>())
                            {
                                string cellValue = cell.CellValue?.Text;
                                if (string.IsNullOrEmpty(cellValue)) continue;

                                if (cell.DataType != null && cell.DataType.Value == CellValues.SharedString && sharedStringTable != null)
                                {
                                    int index = int.Parse(cellValue);
                                    var element = sharedStringTable.ElementAt(index);
                                    if (element != null)
                                    {
                                        text.Append(element.InnerText).Append(" ");
                                    }
                                }
                                else
                                {
                                    text.Append(cellValue).Append(" ");
                                }
                            }
                            text.AppendLine();
                        }
                    }
                }
                return text.ToString().Trim();
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error al extraer texto del Excel {filePath}: {ex.Message}");
                return string.Empty;
            }
        }
    }
}
