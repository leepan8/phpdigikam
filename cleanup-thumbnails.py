#!/usr/bin/python
#
# Scan for obsolete thumbnail images
#
import os
import sys
from urlparse import urlparse
from urllib2 import unquote
from PIL import Image
from optparse import OptionParser

def cleanup(path, delete=False, verbosity=1):
	"""Scan thumbnails below path and delete obsolete files."""
	base_path = os.path.expanduser("~/.thumbnails")
	for root, dirs, files in os.walk(base_path):
		if verbosity >= 2:
			print "ENTER\t%s" % (root,)
		for f in files:
			fp = os.path.join(root, f)
			if verbosity >= 3:
				print "CHECK\t%s" % (fp,)
			try:
				im = Image.open(fp)
			except IOError, e:
				print >>sys.stderr, "FAIL_OPEN\t%s" % (fp,)
				continue
			for uri_key in ("Thumb::URI", "Thumb::Uri"):
				try:
					uri = im.info[uri_key]
					break
				except KeyError, e:
					pass
			else:
				print >>sys.stderr, "NO_URI\t%s\t%r" % (fp, im.info)
				os.unlink(fp)
				continue
			up = urlparse(uri)
			p = unquote(up.path)
			if not os.path.exists(p):
				if verbosity >= 1 or not delete:
					print "GONE\t%s\t%s" % (fp, p)
				if delete:
					try:
						os.unlink(fp)
					except IOError, e:
						print >>sys.stderr, "FAIL_UNLINK\t%s\t%s" % (fp, p)
				continue

if __name__ == '__main__':
	usage = "usage: %prog [options] path"
	parser = OptionParser(usage=usage)
	parser.add_option("-d", "--delete",
			action="store_true", dest="delete",
			help="Delete thumbnails of images, which are no longer present.")
	parser.add_option("-v", "--verbose",
			action="count", dest="verbosity",
			help="print additional messages to stdout")
	parser.add_option("-q", "--quiet",
			action="store_const", const=0, dest="verbosity",
			help="don't print status messages to stdout")
	parser.set_defaults(verbosity=1, delete=False)
	options, args = parser.parse_args()
	try:
		path = args[0]
	except IndexError, e:
		parser.error("missing path")

	try:
		cleanup(path, options.delete, options.verbosity)
	except KeyboardInterrupt:
		print "ABORT"
