using System.Security.Claims;
using Microsoft.AspNetCore;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Mvc;
using OpenIddict.Abstractions;
using OpenIddict.Server.AspNetCore;
using Microsoft.IdentityModel.Tokens;
using static OpenIddict.Abstractions.OpenIddictConstants;

namespace Idp.Controllers;

public class AuthorizationController : Controller
{
    private readonly IOpenIddictScopeManager _scopeManager;

    public AuthorizationController(IOpenIddictScopeManager scopeManager)
        => _scopeManager = scopeManager;

    [HttpGet("~/connect/authorize")]
    [HttpPost("~/connect/authorize")]
    [IgnoreAntiforgeryToken]
    public async Task<IActionResult> Authorize()
    {
        var request = HttpContext.GetOpenIddictServerRequest()
            ?? throw new InvalidOperationException("Cannot retrieve OpenIddict request.");

        var result = await HttpContext.AuthenticateAsync(CookieAuthenticationDefaults.AuthenticationScheme);

        if (!result.Succeeded)
        {
            return Challenge(
                authenticationSchemes: CookieAuthenticationDefaults.AuthenticationScheme,
                properties: new AuthenticationProperties
                {
                    RedirectUri = Request.PathBase + Request.Path + QueryString.Create(
                        Request.HasFormContentType
                            ? Request.Form.ToList()
                            : Request.Query.ToList())
                });
        }

        var user = result.Principal;

        var identity = new ClaimsIdentity(
            authenticationType: TokenValidationParameters.DefaultAuthenticationType,
            nameType: Claims.Name,
            roleType: Claims.Role);

        identity.SetClaim(Claims.Subject, user.FindFirstValue(ClaimTypes.NameIdentifier));
        identity.SetClaim(Claims.Name,    user.FindFirstValue("name") ?? user.Identity?.Name);
        identity.SetClaim(Claims.Email,   user.FindFirstValue(ClaimTypes.Email));

        identity.SetScopes(request.GetScopes());

        //var resources = await _scopeManager.ListResourcesAsync(identity.GetScopes()).ToListAsync();
        var resources = new List<string>();
        await foreach (var resource in _scopeManager.ListResourcesAsync(identity.GetScopes()))
        {
            resources.Add(resource);
        }
        //identity.SetResources(resources);

        identity.SetDestinations(claim => claim.Type switch
        {
            Claims.Name or Claims.Email =>
                new[] { Destinations.AccessToken, Destinations.IdentityToken },
            _ => new[] { Destinations.AccessToken }
        });

        return SignIn(
            new ClaimsPrincipal(identity),
            OpenIddictServerAspNetCoreDefaults.AuthenticationScheme);
    }

    [HttpPost("~/connect/token")]
    public async Task<IActionResult> Exchange()
    {
        var request = HttpContext.GetOpenIddictServerRequest()
            ?? throw new InvalidOperationException("Cannot retrieve OpenIddict request.");

        if (!request.IsAuthorizationCodeGrantType())
            throw new InvalidOperationException("Unsupported grant type.");

        var result = await HttpContext.AuthenticateAsync(
            OpenIddictServerAspNetCoreDefaults.AuthenticationScheme);

        var principal = result.Principal!;

        principal.SetDestinations(claim => claim.Type switch
        {
            Claims.Name or Claims.Email =>
                new[] { Destinations.AccessToken, Destinations.IdentityToken },
            _ => new[] { Destinations.AccessToken }
        });

        return SignIn(principal, OpenIddictServerAspNetCoreDefaults.AuthenticationScheme);
    }

    [HttpGet("~/connect/userinfo")]
    public async Task<IActionResult> Userinfo()
    {
        var principal = (await HttpContext.AuthenticateAsync(
            OpenIddictServerAspNetCoreDefaults.AuthenticationScheme)).Principal;

        return Ok(new
        {
            sub   = principal?.FindFirstValue(Claims.Subject),
            name  = principal?.FindFirstValue(Claims.Name),
            email = principal?.FindFirstValue(Claims.Email),
        });
    }

    [HttpGet("~/connect/logout")]
    [HttpPost("~/connect/logout")]
    public async Task<IActionResult> Logout()
    {
        await HttpContext.SignOutAsync(CookieAuthenticationDefaults.AuthenticationScheme);

        return SignOut(
            authenticationSchemes: OpenIddictServerAspNetCoreDefaults.AuthenticationScheme,
            properties: new AuthenticationProperties { RedirectUri = "/" });
    }
}