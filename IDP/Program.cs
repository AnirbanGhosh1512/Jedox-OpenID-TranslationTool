using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.EntityFrameworkCore;
using Idp;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddControllersWithViews();

builder.Services.AddDbContext<AppDbContext>(options =>
{
    options.UseInMemoryDatabase("IdpDb");
    options.UseOpenIddict();
});

builder.Services.AddAuthentication(CookieAuthenticationDefaults.AuthenticationScheme)
    .AddCookie(options =>
    {
        options.LoginPath = "/account/login";
    });

builder.Services.AddOpenIddict()
    .AddCore(options =>
        options.UseEntityFrameworkCore().UseDbContext<AppDbContext>())
    .AddServer(options =>
    {
        options.SetAuthorizationEndpointUris("/connect/authorize")
               .SetTokenEndpointUris("/connect/token")
               .SetUserinfoEndpointUris("/connect/userinfo")
               .SetLogoutEndpointUris("/connect/logout");

        options.AllowAuthorizationCodeFlow()
               .RequireProofKeyForCodeExchange();

        options.AddDevelopmentEncryptionCertificate()
               .AddDevelopmentSigningCertificate();

        options.SetIssuer(new Uri("http://localhost:8090"))
       .AddDevelopmentEncryptionCertificate()
       .AddDevelopmentSigningCertificate();

        options.UseAspNetCore()
               .EnableAuthorizationEndpointPassthrough()
               .EnableTokenEndpointPassthrough()
               .EnableUserinfoEndpointPassthrough()
               .EnableLogoutEndpointPassthrough()
               .DisableTransportSecurityRequirement();  // ← allows HTTP in dev/Docker

        options.DisableAccessTokenEncryption();
    })
    .AddValidation(options =>
    {
        options.UseLocalServer();
        options.UseAspNetCore();
    });

builder.Services.AddHostedService<SeedWorker>();

var app = builder.Build();

app.UseStaticFiles();
app.UseRouting();
app.UseAuthentication();
app.UseAuthorization();
app.MapControllerRoute("default", "{controller=Account}/{action=Login}/{id?}");

app.Run();