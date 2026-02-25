# To learn more about how to use Nix to configure your environment
# see: https://firebase.google.com/docs/studio/customize-workspace
{ pkgs, ... }: {
  # Which nixpkgs channel to use.
  channel = "stable-25.05"; # or "unstable"

  # Use https://search.nixos.org/packages to find packages
  packages = [
    pkgs.apacheHttpd_2_4
    pkgs.apacheHttpdPackages_2_4.apacheHttpd
    pkgs.php81
    pkgs.php81Extensions.xdebug
    pkgs.php81Extensions.mysqli
    pkgs.php81Extensions.pdo
    pkgs.php81Extensions.pdo_mysql
    pkgs.php81Packages.composer
    pkgs.mysql80
    pkgs.nodejs_24
  ];
  # Sets environment variables in the workspace
  env = {};
  idx = {
    # Search for the extensions you want on https://open-vsx.org/ and use "publisher.id"
    extensions = [
      "bmewburn.vscode-intelephense-client"
      "felixfbecker.php-debug"
      "zobo.php-intellisense"
      # "vscodevim.vim"
      "ms-vscode.js-debug"
      "usernamehw.errorlens"
      "dbaeumer.vscode-eslint"
      "formulahendry.auto-rename-tag"
      "esbenp.prettier-vscode"
      "google.gemini-cli-vscode-ide-companion"
    ];

    # Enable previews
    previews = {
      enable = true;
      previews = {
        web = {
          # Example: run "npm run dev" with PORT set to IDX's defined port for previews,
          # and show it in IDX's web preview panel
          # cwd = "/home/user/cardeco";
          command = [ "php" "-S" "0.0.0.0:$PORT" "-t" "/home/user/cardeco" "router.php" ];
          # command = [ "httpd -D FOREGROUND -f httpd.conf" ];
          manager = "web";
          env = {
            PORT = "8080";
          };
        };
      };
    };

    # Workspace lifecycle hooks
    workspace = {
      # Runs when a workspace is first created
      onCreate = {
        # Example: install JS dependencies from NPM
        composer-install = "XDEBUG_MODE=off composer install --no-autoloader --no-interaction && composer dumpautoload --no-interaction";
      };
      # Runs when the workspace is (re)started
      onStart = {
        # Example: start a background task to watch and re-build backend code
        # watch-backend = "npm run watch-backend";
      };
    };
  };
}