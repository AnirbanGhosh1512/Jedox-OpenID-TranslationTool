FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY IDP.csproj .
RUN dotnet restore
COPY . .
RUN dotnet publish -c Release -o /app/publish

FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS runtime
WORKDIR /app
COPY --from=build /app/publish .
ENV ASPNETCORE_URLS=http://0.0.0.0:8090
EXPOSE 8090
ENTRYPOINT ["dotnet", "IDP.dll"]