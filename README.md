## [GovHub](http://www.govhub.org)'s Census Shape Converter

The Census Shape Converter, (or CSC, for lack of a better name,) helps decipher the vast U.S. government repository of [map shapes from the 2010 census](http://www.census.gov/geo/www/tiger/tgrshp2010/tgrshp2010.html) into .kml files, a more universal format.

Currently, CSC does these things:

1. Grabs an archive from the U.S. Census FTP archive.
2. Converts your choice of Congressional Districts, States, Counties, or Localities to .kml format
3. Separates the different maps into a coherent file structure.
4. (Optionally) reduces the size of the .kml file using the [Douglas-Peuker algorithm](http://www.phpriot.com/articles/reducing-map-path-douglas-peucker-algorithm).

## Installation

CSC is designed to be run from the command-line, so there's no trickery to installing it.

**You *will* have to install its one dependency**, the [GDAL/OGR Binaries](http://trac.osgeo.org/gdal/wiki/DownloadingGdalBinaries), which are used for conversion to .kml. Follow the instructions in the GDAL Readme to add ogr2ogr to your `~/.bash-profile`.

After that, just run `php convert.php` in the command-line, and the script will guide you through the rest.

#### @Todo

- Support other regions, e.g. school districts, zip codes, etc.

##### Credits

- [Jlogsdon's php-cli-tools](https://github.com/jlogsdon/php-cli-tools)
- [Geospatial Data Abstraction Library](http://www.gdal.org/index.html)
- [The U.S. Census Bureau](http://www.census.gov/)