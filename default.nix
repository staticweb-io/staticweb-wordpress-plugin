{ sources ? import ./nix/sources.nix, pkgs ? import sources.nixpkgs { } }:
with pkgs;
mkShell {
    buildInputs =  [
        php81
        php81Packages.composer
    ];
}