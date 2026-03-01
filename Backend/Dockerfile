FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY TranslationApi.csproj .
RUN dotnet restore
COPY . .
RUN dotnet publish -c Release -o /app/publish

FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS runtime
WORKDIR /app
COPY --from=build /app/publish .
EXPOSE 8091
ENTRYPOINT ["dotnet", "TranslationApi.dll"]