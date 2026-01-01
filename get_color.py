import cv2
import numpy as np
img = cv2.imread('/Users/kevinqi/.gemini/antigravity/brain/babaf1e7-2fd1-4d35-a138-1293ffafd9df/media__1778996845858.png')
# The image is the full screenshot. 
# Let's find the button. The text is "Save Settings".
# We can just print the color of a specific patch if we can't do OCR easily, or just crop and find the dominant colors.
colors, counts = np.unique(img.reshape(-1, img.shape[2]), axis=0, return_counts=True)
# filter out white, black, grey
for c, count in zip(colors, counts):
    if count > 500:
        print(c, count)
