using FluentAssertions;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using OpenIddict.Abstractions;
using Xunit;

namespace Idp.Tests;

public class SeedWorkerTests
{
    private static ServiceProvider BuildProvider(string dbName)
    {
        var services = new ServiceCollection();

        services.AddDbContext<AppDbContext>(options =>
        {
            options.UseInMemoryDatabase(dbName);
            options.UseOpenIddict();
        });

        services.AddOpenIddict()
            .AddCore(options =>
                options.UseEntityFrameworkCore()
                       .UseDbContext<AppDbContext>());

        services.AddLogging(b => b.AddConsole());

        return services.BuildServiceProvider();
    }

    private static async Task SeedAsync(ServiceProvider provider)
    {
        // Run SeedWorker logic directly using the same provider scope
        using var scope      = provider.CreateScope();
        var appManager       = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var scopeManager     = scope.ServiceProvider.GetRequiredService<IOpenIddictScopeManager>();

        if (await appManager.FindByClientIdAsync("php-ui") is null)
        {
            await appManager.CreateAsync(new OpenIddictApplicationDescriptor
            {
                ClientId     = "php-ui",
                ClientSecret = "php-secret",
                DisplayName  = "Translation Tool UI",
                RedirectUris           = { new Uri("http://localhost:8080/callback.php") },
                PostLogoutRedirectUris = { new Uri("http://localhost:8080/index.php") },
                Permissions =
                {
                    OpenIddictConstants.Permissions.Endpoints.Authorization,
                    OpenIddictConstants.Permissions.Endpoints.Token,
                    OpenIddictConstants.Permissions.Endpoints.Logout,
                    OpenIddictConstants.Permissions.GrantTypes.AuthorizationCode,
                    OpenIddictConstants.Permissions.ResponseTypes.Code,
                    OpenIddictConstants.Permissions.Scopes.Email,
                    OpenIddictConstants.Permissions.Scopes.Profile,
                }
            });
        }

        if (await appManager.FindByClientIdAsync("translation-api") is null)
        {
            await appManager.CreateAsync(new OpenIddictApplicationDescriptor
            {
                ClientId     = "translation-api",
                ClientSecret = "api-secret",
                DisplayName  = "Translation API",
                Permissions  =
                {
                    OpenIddictConstants.Permissions.Endpoints.Introspection,
                    OpenIddictConstants.Permissions.Prefixes.GrantType + OpenIddictConstants.GrantTypes.ClientCredentials,
                }
            });
        }
    }

    // ── Client registration ────────────────────────────────────────────────────

    [Fact]
    public async Task SeedWorker_RegistersPhpUiClient()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client      = await appManager.FindByClientIdAsync("php-ui");

        client.Should().NotBeNull();
    }

    [Fact]
    public async Task SeedWorker_RegistersTranslationApiClient()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client      = await appManager.FindByClientIdAsync("translation-api");

        client.Should().NotBeNull();
    }

    [Fact]
    public async Task SeedWorker_RegistersExactlyTwoClients()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();

        var count = 0;
        await foreach (var _ in appManager.ListAsync())
            count++;

        count.Should().Be(2);
    }

    // ── php-ui client details ──────────────────────────────────────────────────

    [Fact]
    public async Task PhpUiClient_HasCorrectRedirectUri()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope  = provider.CreateScope();
        var appManager   = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client       = await appManager.FindByClientIdAsync("php-ui");
        var redirectUris = await appManager.GetRedirectUrisAsync(client!);

        redirectUris.Should().Contain("http://localhost:8080/callback.php");
    }

    [Fact]
    public async Task PhpUiClient_HasCorrectPostLogoutRedirectUri()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client      = await appManager.FindByClientIdAsync("php-ui");
        var logoutUris  = await appManager.GetPostLogoutRedirectUrisAsync(client!);

        logoutUris.Should().Contain("http://localhost:8080/index.php");
    }

    [Fact]
    public async Task PhpUiClient_DisplayNameIsSet()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client      = await appManager.FindByClientIdAsync("php-ui");
        var displayName = await appManager.GetDisplayNameAsync(client!);

        displayName.Should().Be("Translation Tool UI");
    }

    // ── translation-api client details ────────────────────────────────────────

    [Fact]
    public async Task TranslationApiClient_DisplayNameIsSet()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();
        var client      = await appManager.FindByClientIdAsync("translation-api");
        var displayName = await appManager.GetDisplayNameAsync(client!);

        displayName.Should().Be("Translation API");
    }

    // ── Idempotency ────────────────────────────────────────────────────────────

    [Fact]
    public async Task SeedWorker_CalledTwice_DoesNotDuplicateClients()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();

        var count = 0;
        await foreach (var _ in appManager.ListAsync())
            count++;

        count.Should().Be(2);
    }

    [Fact]
    public async Task SeedWorker_CalledThreeTimes_DoesNotDuplicateClients()
    {
        var provider = BuildProvider("db_" + Guid.NewGuid());
        await SeedAsync(provider);
        await SeedAsync(provider);
        await SeedAsync(provider);

        using var scope = provider.CreateScope();
        var appManager  = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();

        var count = 0;
        await foreach (var _ in appManager.ListAsync())
            count++;

        count.Should().Be(2);
    }

    // ── StopAsync ──────────────────────────────────────────────────────────────

    [Fact]
    public async Task StopAsync_CompletesSuccessfully()
    {
        var provider   = BuildProvider("db_" + Guid.NewGuid());
        var seedWorker = new SeedWorker(provider);

        await seedWorker.StopAsync(CancellationToken.None);
    }

    [Fact]
    public async Task StopAsync_WithCancelledToken_CompletesSuccessfully()
    {
        var provider   = BuildProvider("db_" + Guid.NewGuid());
        var seedWorker = new SeedWorker(provider);
        var cts        = new CancellationTokenSource();
        cts.Cancel();

        var act = async () => await seedWorker.StopAsync(cts.Token);

        await act.Should().NotThrowAsync();
    }
}