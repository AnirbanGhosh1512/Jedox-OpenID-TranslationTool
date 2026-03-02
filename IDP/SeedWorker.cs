using OpenIddict.Abstractions;
using static OpenIddict.Abstractions.OpenIddictConstants;

namespace Idp;

public class SeedWorker : IHostedService
{
    private readonly IServiceProvider _services;

    public SeedWorker(IServiceProvider services) => _services = services;

    public async Task StartAsync(CancellationToken ct)
    {
        using var scope = _services.CreateScope();

        var appManager   = scope.ServiceProvider.GetRequiredService<IOpenIddictApplicationManager>();

        // PHP UI client
        if (await appManager.FindByClientIdAsync("php-ui", ct) is null)
        {
            await appManager.CreateAsync(new OpenIddictApplicationDescriptor
            {
                ClientId     = "php-ui",
                ClientSecret = "php-secret",
                DisplayName  = "Translation Tool UI",
                RedirectUris          = { new Uri("http://localhost:8080/callback.php") },
                PostLogoutRedirectUris = { new Uri("http://localhost:8080/index.php") },
                Permissions =
                {
                    Permissions.Endpoints.Authorization,
                    Permissions.Endpoints.Token,
                    Permissions.Endpoints.Logout,
                    Permissions.GrantTypes.AuthorizationCode,
                    Permissions.ResponseTypes.Code,
                    //Permissions.Scopes.OpenId,
                    //Permissions.Prefixes.Scope + "openid",
                    Permissions.Scopes.Email,
                    Permissions.Scopes.Profile,
                    //Permissions.Prefixes.Scope + "api",
                }
            }, ct);
        }

        // API introspection client
        if (await appManager.FindByClientIdAsync("translation-api", ct) is null)
        {
            await appManager.CreateAsync(new OpenIddictApplicationDescriptor
            {
                ClientId     = "translation-api",
                ClientSecret = "api-secret",
                DisplayName  = "Translation API",
                Permissions  =
                {
                    Permissions.Endpoints.Introspection,
                    Permissions.Prefixes.GrantType + GrantTypes.ClientCredentials,
                }
            }, ct);
        }
    }

    public Task StopAsync(CancellationToken ct) => Task.CompletedTask;
}