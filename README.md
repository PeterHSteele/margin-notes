# Margin Notes
**Contributors**: peterste

**Tags**: Annotation  

**Requires at least**: 5.2

**Tested up to**: 5.2

**Requires PHP**: 7.1

**Stable tag**: 5.2

**License**: GPLv2 or later

**License URI**: http://www.gnu.org/licenses/gpl-2.0.html
 
Allows subscribers to add annotations to articles on your site.

## Description
Subscribers can add commentary on pages or posts on your site. Unlike comments, only the user who created the annotation can 
see what they wrote. There is no affect on the site's appearance to the average visitor. Margin Notes allows for several display options and has color customization to help with theme compatibility.

## Installation
1. Upload this folder with its contents to your plugins directory.
2. Activate Margin Notes from the list in Dashboard -> Plugins
3. Check Settings -> Discussion to configure your color and display settings. They're not too painful, promise.

## Frequently Asked Questions
### Help, the annotations are showing up way above or below my content and screwing up my site!
It's important to choose the correct html container for the annotations. In general, you want to pick the "outer" content container, which in many themes is something like `div.content-area`. This is in contrast to the "inner" content container, which is often something like `div.site-main` and houses the actual paragraphs and images that make up most pages. However, every theme is different and some real familiarity  with your theme is necessary to make the correct choice. That's why there is another option: tooltip display. Select 'tooltips' in the settings menu (Settings -> Discussion) and the annotations will display in a popup immediately on top of their source text. With this setting, you don't have to worry about choosing the html container.

## Updates
* 1.0.0
First Version

## Changelog
* 1.0.0 
First Version
