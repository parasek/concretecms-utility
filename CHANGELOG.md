# 3.0.0

- Now, getSliderImage() and getSliderImagesByFileSet() methods consider video files as valid. New property isVideo was
  added to returned data. Additionally video object will be returned that consists additional information (like
  dimensions, duration etc.) retrieved using getID3 library.
- All properties of FileUtility and ImageUtility are now public.
- Method isValidImage() has been refactored to isImage().

# 2.0.0

- Changed text alignment attribute to select (from checkbox) in `getSliderImage()` and `getSliderImagesByFileSet()`
  methods.
  Those methods now rely on using `slide_text_alignment` (select type) attribute instead `slide_right_alignment` (
  checkbox type) attribute.
  Data objects returned by those methods now contains `textAlignment` property instead of `rightAlignment`.

# 1.1.0

- Add service provider, see README.md

# 1.0.1

- Fix returned type hints for arrays
- Fix dimensions for svg files
- Fix code responsible for checking if image is valid (PHP operator precedence)

# 1.0.0

- Initial release for Concrete 9 and PHP 8.1

