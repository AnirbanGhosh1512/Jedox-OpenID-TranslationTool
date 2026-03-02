using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using FluentAssertions;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using TranslationApi.Controllers;
using TranslationApi.Data;
using TranslationApi.DTOs;
using TranslationApi.Models;
using Xunit;

namespace Backend.Tests;

public class SidsControllerTests
{
    private AppDbContext CreateDbContext()
    {
        var options = new DbContextOptionsBuilder<AppDbContext>()
            .UseInMemoryDatabase(Guid.NewGuid().ToString())
            .Options;

        return new AppDbContext(options);
    }

    private static Sid CreateSid(string key, string text)
    {
        return new Sid
        {
            SidKey = key,
            DefaultText = text,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow,
            Translations = new List<Translation>()
        };
    }

    // ----------------------------
    // GET ALL
    // ----------------------------

    [Fact]
    public async Task GetAll_Returns_Empty_List_When_No_Data()
    {
        var context = CreateDbContext();
        var controller = new SidsController(context);

        var result = await controller.GetAll();

        var ok = result.Result as OkObjectResult;
        ok.Should().NotBeNull();

        var data = ok!.Value as List<SidSummaryDto>;
        data.Should().BeEmpty();
    }

    [Fact]
    public async Task GetAll_Returns_Ordered_List()
    {
        var context = CreateDbContext();
        context.Sids.Add(CreateSid("B", "Second"));
        context.Sids.Add(CreateSid("A", "First"));
        await context.SaveChangesAsync();

        var controller = new SidsController(context);
        var result = await controller.GetAll();

        var ok = result.Result as OkObjectResult;
        var data = ok!.Value as List<SidSummaryDto>;

        data!.First().Sid.Should().Be("A");
        data.Last().Sid.Should().Be("B");
    }

    // ----------------------------
    // GET BY SID
    // ----------------------------

    [Fact]
    public async Task GetBySid_Returns_NotFound_When_Missing()
    {
        var context = CreateDbContext();
        var controller = new SidsController(context);

        var result = await controller.GetBySid("UNKNOWN");

        result.Result.Should().BeOfType<NotFoundResult>();
    }

    [Fact]
    public async Task GetBySid_Returns_Detail_With_Translations()
    {
        var context = CreateDbContext();
        var sid = CreateSid("X", "Hello");

        sid.Translations.Add(new Translation
        {
            LangId = "de",
            Text = "Hallo",
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        });

        context.Sids.Add(sid);
        await context.SaveChangesAsync();

        var controller = new SidsController(context);
        var result = await controller.GetBySid("X");

        var ok = result.Result as OkObjectResult;
        var dto = ok!.Value as SidDetailDto;

        dto!.Sid.Should().Be("X");
        dto.Translations.Should().HaveCount(1);
    }

    // ----------------------------
    // CREATE
    // ----------------------------

    [Fact]
    public async Task Create_Returns_Conflict_When_Sid_Exists()
    {
        var context = CreateDbContext();
        context.Sids.Add(CreateSid("A", "Existing"));
        await context.SaveChangesAsync();

        var controller = new SidsController(context);

        var request = new CreateSidRequest("A", "New", null);
        var result = await controller.Create(request);

        result.Result.Should().BeOfType<ConflictObjectResult>();
    }

    [Fact]
    public async Task Create_Creates_Sid_With_Translations()
    {
        var context = CreateDbContext();
        var controller = new SidsController(context);

        var request = new CreateSidRequest(
            "NEW",
            "Welcome",
            new List<TranslationDto>
            {
                new TranslationDto("fr", "Bienvenue")
            });

        var result = await controller.Create(request);

        result.Result.Should().BeOfType<CreatedAtActionResult>();
        context.Sids.Count().Should().Be(1);
        context.Translations.Count().Should().Be(1);
    }

    // ----------------------------
    // UPSERT TRANSLATION
    // ----------------------------

    [Fact]
    public async Task UpsertTranslation_Returns_NotFound_When_Sid_Missing()
    {
        var context = CreateDbContext();
        var controller = new SidsController(context);

        var result = await controller.UpsertTranslation("NOPE", "de", new UpsertTranslationRequest("Hallo"));

        result.Should().BeOfType<NotFoundObjectResult>();
    }

    [Fact]
    public async Task UpsertTranslation_Adds_New_Translation()
    {
        var context = CreateDbContext();
        context.Sids.Add(CreateSid("A", "Hi"));
        await context.SaveChangesAsync();

        var controller = new SidsController(context);

        var result = await controller.UpsertTranslation("A", "de", new UpsertTranslationRequest("Hallo"));

        result.Should().BeOfType<NoContentResult>();
        context.Translations.Count().Should().Be(1);
    }

    [Fact]
    public async Task UpsertTranslation_Updates_Existing_Translation()
    {
        var context = CreateDbContext();
        var sid = CreateSid("A", "Hi");

        sid.Translations.Add(new Translation
        {
            LangId = "de",
            Text = "Old",
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        });

        context.Sids.Add(sid);
        await context.SaveChangesAsync();

        var controller = new SidsController(context);

        await controller.UpsertTranslation("A", "de", new UpsertTranslationRequest("New"));

        context.Translations.First().Text.Should().Be("New");
    }

    // ----------------------------
    // DELETE
    // ----------------------------

    [Fact]
    public async Task Delete_Returns_NotFound_When_Missing()
    {
        var context = CreateDbContext();
        var controller = new SidsController(context);

        var result = await controller.Delete("NONE");

        result.Should().BeOfType<NotFoundResult>();
    }

    [Fact]
    public async Task Delete_Removes_Sid()
    {
        var context = CreateDbContext();
        context.Sids.Add(CreateSid("DEL", "Bye"));
        await context.SaveChangesAsync();

        var controller = new SidsController(context);
        var result = await controller.Delete("DEL");

        result.Should().BeOfType<NoContentResult>();
        context.Sids.Count().Should().Be(0);
    }

    // ----------------------------
    // GET VIEW
    // ----------------------------

    [Fact]
    public async Task GetView_Returns_Fallback_When_Translation_Missing()
    {
        var context = CreateDbContext();
        context.Sids.Add(CreateSid("A", "Hello"));
        await context.SaveChangesAsync();

        var controller = new SidsController(context);

        var actionResult = await controller.GetView("de");

        var okResult = actionResult.Result as OkObjectResult;
        okResult.Should().NotBeNull();

        var list = okResult!.Value as IEnumerable<object>;
        list.Should().NotBeNull();
        list!.Count().Should().Be(1);
    }

    [Fact]
    public async Task GetView_Returns_Translated_Text_When_Exists()
    {
        var context = CreateDbContext();
        var sid = CreateSid("A", "Hello");

        sid.Translations.Add(new Translation
        {
            LangId = "de",
            Text = "Hallo",
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        });

        context.Sids.Add(sid);
        await context.SaveChangesAsync();

        var controller = new SidsController(context);

        var actionResult = await controller.GetView("de");

        var okResult = actionResult.Result as OkObjectResult;
        okResult.Should().NotBeNull();

        var list = okResult!.Value as IEnumerable<object>;
        list.Should().NotBeNull();
        list!.Count().Should().Be(1);
    }
}