import os
import xml.dom.minidom

MUSIC_DIR = "music"
SERVER_XML_FILE = "xaseco/musicserver.xml"
CACHE_XML_FILE = "xaseco/musictagscache.xml"


def parse_file_name(file_name):
    """Parse the given music file name into artist and title components"""
    artist, title = file_name[:-4].replace("_", " ").split("-")
    return {"file": file_name, "artist": artist, "title": title}


def get_song_files():
    """Returns a list of song files in the music directory"""
    song_files = []
    for file_name in os.listdir(MUSIC_DIR):
        if file_name.endswith(".ogg"):
            song_files.append(parse_file_name(file_name))
    return song_files


def update_music_server_xml(song_files):
    """Updates the music server XML file with the given song files"""
    doc = xml.dom.minidom.parse(SERVER_XML_FILE)
    existing_songs = [song_node.firstChild.nodeValue for song_node in doc.getElementsByTagName('song')]
    song_files_element = doc.getElementsByTagName("song_files")[0]
    for song_file in song_files:
        if song_file["file"] not in existing_songs:
            file_name = song_file["file"]
            if not any(song.firstChild.nodeValue == file_name for song in song_files_element.getElementsByTagName("song")):
                song_element = doc.createElement("song")
                song_element.appendChild(doc.createTextNode(file_name))
                song_files_element.appendChild(song_element)
    xml_string = doc.toprettyxml(indent="", newl="\n").replace('\n\n', '')
    with open(SERVER_XML_FILE, "w") as f:
        f.write(xml_string)


def update_music_tags_cache_xml(song_files):
    """Updates the music tags cache XML file with the given song files"""
    doc = xml.dom.minidom.parse(CACHE_XML_FILE)
    existing_songs = [song_node.firstChild.nodeValue for song_node in doc.getElementsByTagName('song')]
    tags_element = doc.getElementsByTagName("tags")[0]
    for song_file in song_files:
        if song_file["file"] not in existing_songs:
            file_name = song_file["file"]
            if not any(song.getElementsByTagName("file")[0].firstChild.nodeValue == file_name for song in tags_element.getElementsByTagName("song")):
                song_element = doc.createElement("song")
                file_element = doc.createElement("file")
                file_element.appendChild(doc.createTextNode(file_name))
                song_element.appendChild(file_element)
                title_element = doc.createElement("title")
                title_element.appendChild(doc.createTextNode(song_file["title"]))
                song_element.appendChild(title_element)
                artist_element = doc.createElement("artist")
                artist_element.appendChild(doc.createTextNode(song_file["artist"]))
                song_element.appendChild(artist_element)
                tags_element.appendChild(song_element)
    xml_string = doc.toprettyxml(indent="", newl="\n").replace('\n\n', '')
    with open(CACHE_XML_FILE, "w") as f:
        f.write(xml_string)


if __name__ == "__main__":
    song_files = get_song_files()
    update_music_server_xml(song_files)
    update_music_tags_cache_xml(song_files)
