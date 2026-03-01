namespace TranslationApi.Models;

public class Sid
{
    public int Id { get; set; }
    public string SidKey { get; set; } = "";
    public string DefaultText { get; set; } = "";
    public DateTime CreatedAt { get; set; }
    public DateTime UpdatedAt { get; set; }
    public ICollection<Translation> Translations { get; set; } = new List<Translation>();
}

public class Translation
{
    public int Id { get; set; }
    public int SidId { get; set; }
    public string LangId { get; set; } = "";
    public string Text { get; set; } = "";
    public DateTime CreatedAt { get; set; }
    public DateTime UpdatedAt { get; set; }
    public Sid Sid { get; set; } = null!;
}