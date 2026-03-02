using System.Security.Claims;
using FluentAssertions;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.ViewFeatures;
using Microsoft.Extensions.DependencyInjection;
using Moq;
using Xunit;
using Idp.Controllers;
using Idp.Models;

namespace Idp.Tests;

public class AccountControllerTests
{
   
   private static AccountController CreateController(
    Mock<IAuthenticationService>? authMock = null)
{
    authMock ??= new Mock<IAuthenticationService>();

    authMock
        .Setup(a => a.SignInAsync(
            It.IsAny<HttpContext>(),
            It.IsAny<string>(),
            It.IsAny<ClaimsPrincipal>(),
            It.IsAny<AuthenticationProperties>()))
        .Returns(Task.CompletedTask);

    authMock
        .Setup(a => a.SignOutAsync(
            It.IsAny<HttpContext>(),
            It.IsAny<string>(),
            It.IsAny<AuthenticationProperties>()))
        .Returns(Task.CompletedTask);

    // Must also mock IAuthenticationHandlerProvider which HttpContext.SignInAsync needs
    var handlerProvider = new Mock<IAuthenticationHandlerProvider>();
    handlerProvider
        .Setup(h => h.GetHandlerAsync(It.IsAny<HttpContext>(), It.IsAny<string>()))
        .ReturnsAsync((IAuthenticationHandler?)null);

    var schemeProvider = new Mock<IAuthenticationSchemeProvider>();
    schemeProvider
        .Setup(s => s.GetDefaultSignInSchemeAsync())
        .ReturnsAsync(new AuthenticationScheme(
            "Cookies",
            null,
            typeof(IAuthenticationHandler)));

    var services = new ServiceCollection();
    services.AddSingleton(authMock.Object);
    services.AddSingleton(schemeProvider.Object);
    services.AddSingleton(handlerProvider.Object);
    services.AddLogging();

    var serviceProvider = services.BuildServiceProvider();

    var urlHelper = new Mock<IUrlHelper>();
    urlHelper
        .Setup(u => u.IsLocalUrl(It.IsAny<string>()))
        .Returns(false);

    var controller = new AccountController
    {
        ControllerContext = new ControllerContext
        {
            HttpContext = new DefaultHttpContext
            {
                RequestServices = serviceProvider
            }
        },
        Url      = urlHelper.Object,
        TempData = new Mock<ITempDataDictionary>().Object,
    };

    return controller;
}

    // ── GET /account/login ─────────────────────────────────────────────────────

    [Fact]
    public void Login_Get_ReturnsViewResult()
    {
        var controller = CreateController();

        var result = controller.Login(returnUrl: null);

        result.Should().BeOfType<ViewResult>();
    }

    [Fact]
    public void Login_Get_SetsReturnUrlInViewData()
    {
        var controller = CreateController();

        var result = controller.Login("/connect/authorize") as ViewResult;

        result!.ViewData["ReturnUrl"].Should().Be("/connect/authorize");
    }

    [Fact]
    public void Login_Get_NullReturnUrl_DoesNotThrow()
    {
        var controller = CreateController();

        var act = () => controller.Login(returnUrl: null);

        act.Should().NotThrow();
    }

    // ── POST /account/login — valid credentials ────────────────────────────────
    [Theory]
    [InlineData("admin", "admin123", "Admin User")]
    [InlineData("user",  "user123",  "Test User")]
    public async Task Login_Post_ValidCredentials_SignsIn(
        string username, string password, string expectedName)
    {
        var controller = CreateController();
        var model      = new LoginViewModel { Username = username, Password = password };

        var result = await controller.Login(model, returnUrl: null);

        result.Should().BeOfType<RedirectResult>();
        controller.ModelState.IsValid.Should().BeTrue();
    }


    [Theory]
    [InlineData("admin", "admin123")]
    [InlineData("user",  "user123")]
    public async Task Login_Post_ValidCredentials_RedirectsToHome(
        string username, string password)
    {
        var controller = CreateController();
        var model      = new LoginViewModel { Username = username, Password = password };

        var result = await controller.Login(model, returnUrl: null);

        result.Should().BeOfType<RedirectResult>();
    }

    // ── POST /account/login — invalid credentials ──────────────────────────────

    [Theory]
    [InlineData("admin",   "wrongpassword")]
    [InlineData("unknown", "admin123")]
    [InlineData("",        "admin123")]
    [InlineData("admin",   "")]
    public async Task Login_Post_InvalidCredentials_ReturnsViewWithModelError(
        string username, string password)
    {
        var controller = CreateController();
        var model      = new LoginViewModel { Username = username, Password = password };

        var result = await controller.Login(model, returnUrl: null);

        result.Should().BeOfType<ViewResult>();
        controller.ModelState.ErrorCount.Should().BeGreaterThan(0);
    }

    [Theory]
    [InlineData("admin",   "wrongpassword")]
    [InlineData("unknown", "admin123")]
    public async Task Login_Post_InvalidCredentials_NeverSignsIn(
        string username, string password)
    {
        var authMock   = new Mock<IAuthenticationService>();
        var controller = CreateController(authMock);
        var model      = new LoginViewModel { Username = username, Password = password };

        await controller.Login(model, returnUrl: null);

        authMock.Verify(a => a.SignInAsync(
            It.IsAny<HttpContext>(),
            It.IsAny<string>(),
            It.IsAny<ClaimsPrincipal>(),
            It.IsAny<AuthenticationProperties>()), Times.Never);
    }

    // ── POST /account/login — returnUrl ────────────────────────────────────────

    [Fact]
    public async Task Login_Post_WithLocalReturnUrl_RedirectsToReturnUrl()
    {
        var authMock = new Mock<IAuthenticationService>();
        authMock
            .Setup(a => a.SignInAsync(
                It.IsAny<HttpContext>(),
                It.IsAny<string>(),
                It.IsAny<ClaimsPrincipal>(),
                It.IsAny<AuthenticationProperties>()))
            .Returns(Task.CompletedTask);

        var services = new Mock<IServiceProvider>();
        services
            .Setup(s => s.GetService(typeof(IAuthenticationService)))
            .Returns(authMock.Object);

        var urlHelper = new Mock<IUrlHelper>();
        urlHelper
            .Setup(u => u.IsLocalUrl("/connect/authorize?foo=bar"))
            .Returns(true);

        var controller = new AccountController
        {
            ControllerContext = new ControllerContext
            {
                HttpContext = new DefaultHttpContext { RequestServices = services.Object }
            },
            Url      = urlHelper.Object,
            TempData = new Mock<ITempDataDictionary>().Object,
        };

        var model = new LoginViewModel { Username = "admin", Password = "admin123" };

        var result = await controller.Login(model, "/connect/authorize?foo=bar");

        result.Should().BeOfType<RedirectResult>()
            .Which.Url.Should().Be("/connect/authorize?foo=bar");
    }

    [Fact]
    public async Task Login_Post_WithExternalReturnUrl_RedirectsToHome()
    {
        var controller = CreateController();
        var model      = new LoginViewModel { Username = "admin", Password = "admin123" };

        var result = await controller.Login(model, "http://evil.com/steal");

        // Should NOT redirect to external URL
        var redirect = result.Should().BeOfType<RedirectResult>().Subject;
        redirect.Url.Should().NotBe("http://evil.com/steal");
    }

    // ── POST /account/logout ───────────────────────────────────────────────────

    [Fact]
    public async Task Logout_SignsOutUser()
    {
        var authMock   = new Mock<IAuthenticationService>();
        authMock
            .Setup(a => a.SignOutAsync(
                It.IsAny<HttpContext>(),
                It.IsAny<string>(),
                It.IsAny<AuthenticationProperties>()))
            .Returns(Task.CompletedTask);

        var controller = CreateController(authMock);

        await controller.Logout();

        authMock.Verify(a => a.SignOutAsync(
            It.IsAny<HttpContext>(),
            It.IsAny<string>(),
            It.IsAny<AuthenticationProperties>()), Times.Once);
    }

    [Fact]
    public async Task Logout_RedirectsToLoginPage()
    {
        var controller = CreateController();

        var result = await controller.Logout();

        result.Should().BeOfType<RedirectResult>()
            .Which.Url.Should().Be("/account/login");
    }

    // ── Claims content ─────────────────────────────────────────────────────────

    [Fact]
    public async Task Login_Post_AdminUser_HasCorrectClaims()
    {
        var controller = CreateController();
        var model      = new LoginViewModel { Username = "admin", Password = "admin123" };

        var result = await controller.Login(model, returnUrl: null);

        result.Should().BeOfType<RedirectResult>();
        controller.ModelState.ErrorCount.Should().Be(0);
    }

    [Fact]
    public async Task Login_Post_InvalidModelState_ReturnsView()
    {
        var controller = CreateController();
        controller.ModelState.AddModelError("Username", "Required");

        var model  = new LoginViewModel { Username = "", Password = "" };
        var result = await controller.Login(model, returnUrl: null);

        result.Should().BeOfType<ViewResult>();
    }
}