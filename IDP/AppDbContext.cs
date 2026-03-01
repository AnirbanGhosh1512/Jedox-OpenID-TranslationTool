using Microsoft.EntityFrameworkCore;

namespace Idp;

public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }
}