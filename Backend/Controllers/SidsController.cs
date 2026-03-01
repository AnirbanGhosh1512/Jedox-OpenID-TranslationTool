using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using TranslationApi.Data;
using TranslationApi.DTOs;
using TranslationApi.Models;

namespace TranslationApi.Controllers;

[ApiController]
[Route("api/[controller]")]
[Authorize]
public class SidsController : ControllerBase
{
    private readonly AppDbContext _db;

    public SidsController(AppDbContext db) => _db = db;

    // GET /api/sids
    [HttpGet]
    public async Task<ActionResult<List<SidSummaryDto>>> GetAll()
    {
        var sids = await _db.Sids
            .OrderBy(s => s.SidKey)
            .Select(s => new SidSummaryDto(s.SidKey, s.DefaultText))
            .ToListAsync();

        return Ok(sids);
    }

    // GET /api/sids/view?lang=de-DE
    [HttpGet("view")]
    public async Task<ActionResult<List<object>>> GetView([FromQuery] string lang = "en-US")
    {
        var sids = await _db.Sids
            .Include(s => s.Translations)
            .OrderBy(s => s.SidKey)
            .ToListAsync();

        var result = sids.Select(s =>
        {
            var t = s.Translations.FirstOrDefault(x => x.LangId == lang);
            return new
            {
                sid         = s.SidKey,
                defaultText = s.DefaultText,
                langId      = lang,
                text        = t?.Text ?? s.DefaultText,
                hasFallback = t is null,
            };
        }).ToList();

        return Ok(result);
    }

    // GET /api/sids/{sid}
    [HttpGet("{sid}")]
    public async Task<ActionResult<SidDetailDto>> GetBySid(string sid)
    {
        var entity = await _db.Sids
            .Include(s => s.Translations)
            .FirstOrDefaultAsync(s => s.SidKey == sid);

        if (entity is null) return NotFound();

        return Ok(new SidDetailDto(
            entity.SidKey,
            entity.DefaultText,
            entity.CreatedAt,
            entity.UpdatedAt,
            entity.Translations
                  .Select(t => new TranslationDto(t.LangId, t.Text))
                  .ToList()));
    }

    // POST /api/sids
    [HttpPost]
    public async Task<ActionResult<SidDetailDto>> Create([FromBody] CreateSidRequest req)
    {
        if (await _db.Sids.AnyAsync(s => s.SidKey == req.Sid))
            return Conflict($"SID '{req.Sid}' already exists.");

        var now = DateTime.UtcNow;
        var entity = new Sid
        {
            SidKey      = req.Sid,
            DefaultText = req.DefaultText,
            CreatedAt   = now,
            UpdatedAt   = now,
        };

        if (req.Translations != null)
        {
            foreach (var t in req.Translations)
            {
                entity.Translations.Add(new Translation
                {
                    LangId    = t.LangId,
                    Text      = t.Text,
                    CreatedAt = now,
                    UpdatedAt = now,
                });
            }
        }

        _db.Sids.Add(entity);
        await _db.SaveChangesAsync();

        return CreatedAtAction(nameof(GetBySid), new { sid = entity.SidKey },
            new SidDetailDto(
                entity.SidKey,
                entity.DefaultText,
                entity.CreatedAt,
                entity.UpdatedAt,
                entity.Translations
                      .Select(t => new TranslationDto(t.LangId, t.Text))
                      .ToList()));
    }

    // PUT /api/sids/{sid}/translations/{langId}
    [HttpPut("{sid}/translations/{langId}")]
    public async Task<IActionResult> UpsertTranslation(
        string sid, string langId, [FromBody] UpsertTranslationRequest req)
    {
        var parent = await _db.Sids
            .Include(s => s.Translations)
            .FirstOrDefaultAsync(s => s.SidKey == sid);

        if (parent is null) return NotFound($"SID '{sid}' not found.");

        var existing = parent.Translations.FirstOrDefault(t => t.LangId == langId);
        var now = DateTime.UtcNow;

        if (existing is null)
        {
            parent.Translations.Add(new Translation
            {
                LangId    = langId,
                Text      = req.Text,
                CreatedAt = now,
                UpdatedAt = now,
            });
        }
        else
        {
            existing.Text      = req.Text;
            existing.UpdatedAt = now;
        }

        parent.UpdatedAt = now;
        await _db.SaveChangesAsync();
        return NoContent();
    }

    // DELETE /api/sids/{sid}
    [HttpDelete("{sid}")]
    public async Task<IActionResult> Delete(string sid)
    {
        var entity = await _db.Sids.FirstOrDefaultAsync(s => s.SidKey == sid);
        if (entity is null) return NotFound();

        _db.Sids.Remove(entity);
        await _db.SaveChangesAsync();
        return NoContent();
    }
}