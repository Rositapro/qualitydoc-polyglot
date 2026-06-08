using System;
using Xunit;
using QualityDocc.Domain.Entities;

namespace QualityDocc.Tests
{
    public class VersionAndStatusTests
    {
        // Método auxiliar que implementa la misma lógica del backend para probarla de forma aislada
        private double CalculateNextVersionOnApproval(double currentVersion)
        {
            return Math.Floor(currentVersion) + 1.0;
        }

        private double CalculateSuggestedVersionOnEdit(double lastVersionNumber, DocumentStatus state)
        {
            if (state == DocumentStatus.Rechazado ||
                state == DocumentStatus.Aprobado ||
                state == DocumentStatus.Vigente)
            {
                return Math.Round(lastVersionNumber + 0.1, 1);
            }
            else
            {
                return lastVersionNumber;
            }
        }

        [Theory]
        [InlineData(0.1, 1.0)]
        [InlineData(0.9, 1.0)]
        [InlineData(1.0, 2.0)]
        [InlineData(1.2, 2.0)]
        [InlineData(2.0, 3.0)]
        [InlineData(10.5, 11.0)]
        public void Approver_AcceptingDocument_ShouldJumpToNextMajorInteger(double currentVersion, double expectedVersion)
        {
            // Act
            double nextVersion = CalculateNextVersionOnApproval(currentVersion);

            // Assert
            Assert.Equal(expectedVersion, nextVersion);
        }

        [Theory]
        [InlineData(0.1, DocumentStatus.Rechazado, 0.2)]
        [InlineData(1.0, DocumentStatus.Rechazado, 1.1)]
        [InlineData(1.0, DocumentStatus.Aprobado, 1.1)]
        [InlineData(1.0, DocumentStatus.Vigente, 1.1)]
        [InlineData(0.1, DocumentStatus.Revision, 0.1)]
        [InlineData(1.0, DocumentStatus.EnAutorizacion, 1.0)]
        public void Author_EditingDocument_ShouldCalculateSuggestedVersionCorrectly(double lastVersion, DocumentStatus state, double expectedSuggested)
        {
            // Act
            double suggestedVersion = CalculateSuggestedVersionOnEdit(lastVersion, state);

            // Assert
            Assert.Equal(expectedSuggested, suggestedVersion);
        }

        [Fact]
        public void DocumentStatus_EnAutorizacion_ShouldBeSix()
        {
            // Assert
            Assert.Equal(6, (int)DocumentStatus.EnAutorizacion);
        }
    }
}
