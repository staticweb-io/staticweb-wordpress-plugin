{
  description = "staticweb-wordpress-plugin";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-24.11";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      with import nixpkgs { inherit system; };
      with pkgs; {
        devShells.default =
          mkShell { buildInputs = [ php84 php84Packages.composer ]; };
      });
}
