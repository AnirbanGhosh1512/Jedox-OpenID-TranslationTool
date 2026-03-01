using System.Security.Claims;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Mvc;
using Idp.Models;

namespace Idp.Controllers;

public class AccountController : Controller
{
    // Hardcoded test users — no DB needed for demo
    private static readonly Dictionary<string, (string Password, string Name, string Email)> Users = new()
    {
        ["admin"] = ("admin123", "Admin User", "admin@example.com"),
        ["user"]  = ("user123",  "Test User",  "user@example.com"),
    };

    [HttpGet]
    public IActionResult Login(string? returnUrl = null)
    {
        ViewData["ReturnUrl"] = returnUrl;
        return View();
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Login(LoginViewModel model, string? returnUrl = null)
    {
        if (!ModelState.IsValid)
            return View(model);

        if (!Users.TryGetValue(model.Username, out var user) || user.Password != model.Password)
        {
            ModelState.AddModelError("", "Invalid username or password.");
            return View(model);
        }

        var claims = new List<Claim>
        {
            new(ClaimTypes.NameIdentifier, model.Username),
            new(ClaimTypes.Name,           model.Username),
            new(ClaimTypes.Email,          user.Email),
            new("name",                    user.Name),
        };

        var identity  = new ClaimsIdentity(claims, CookieAuthenticationDefaults.AuthenticationScheme);
        var principal = new ClaimsPrincipal(identity);

        await HttpContext.SignInAsync(CookieAuthenticationDefaults.AuthenticationScheme, principal);

        if (!string.IsNullOrEmpty(returnUrl) && Url.IsLocalUrl(returnUrl))
            return Redirect(returnUrl);

        return Redirect("/");
    }

    [HttpPost, ValidateAntiForgeryToken]
    public async Task<IActionResult> Logout()
    {
        await HttpContext.SignOutAsync(CookieAuthenticationDefaults.AuthenticationScheme);
        return Redirect("/account/login");
    }
}