using System.Text.Json.Serialization;

namespace QualityDocc.MVC.Models
{
    public class APILoginRequest
    {
        [JsonPropertyName("email")]
        public string Email { get; set; }
        [JsonPropertyName("password")]
        public string Password { get; set; }
    }
}

//jhsajhsj
