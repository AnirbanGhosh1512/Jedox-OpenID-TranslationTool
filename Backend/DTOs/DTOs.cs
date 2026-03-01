namespace TranslationApi.DTOs;

public record TranslationDto(string LangId, string Text);

public record SidSummaryDto(string Sid, string DefaultText);

public record SidDetailDto(
    string Sid,
    string DefaultText,
    DateTime CreatedAt,
    DateTime UpdatedAt,
    List<TranslationDto> Translations);

public record CreateSidRequest(
    string Sid,
    string DefaultText,
    List<TranslationDto>? Translations);

public record UpsertTranslationRequest(string Text);