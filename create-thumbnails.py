#!/usr/bin/python

## Copyright 2011 Mike Willis (http://blogs.warwick.ac.uk/mikewillis/contact/)
##                Laurent Bovet <laurent.bovet@windmaster.ch>
##
##  This file is part of phpdigikam
##
##  It has been gratefully stolen from:
##  http://blogs.warwick.ac.uk/mikewillis/entry/generating_freedesktoporg_spec/
##
##  phpdigikam is free software; you can redistribute it
##  and/or modify it under the terms of the GNU General
##  Public License as published by the Free Software Foundation;
##  either version 2, or (at your option)
##  any later version.
##  
##  This program is distributed in the hope that it will be useful,
##  but WITHOUT ANY WARRANTY; without even the implied warranty of
##  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
##  GNU General Public License for more details.

import gnome.ui
import gnomevfs
import time
import os
import os.path
import sys

dir=sys.argv[1]

thumbFactory = gnome.ui.ThumbnailFactory(gnome.ui.THUMBNAIL_SIZE_LARGE)
for subdir, dirs, files in os.walk(dir):
 for file in files:
  path = os.path.join(subdir, file)
  uri = gnomevfs.get_uri_from_local_path(path)  
  mime = gnomevfs.get_mime_type(path)
  mtime = int(os.path.getmtime(path))
  if not os.path.exists(gnome.ui.thumbnail_path_for_uri(uri, gnome.ui.THUMBNAIL_SIZE_LARGE)) and thumbFactory.can_thumbnail(uri ,mime, 0):
      print "Generating for "+uri
      thumbnail=thumbFactory.generate_thumbnail(uri, mime)
      if thumbnail != None:                    
          thumbFactory.save_thumbnail(thumbnail, uri, mtime) 
  else:
      print "Skip "+uri
