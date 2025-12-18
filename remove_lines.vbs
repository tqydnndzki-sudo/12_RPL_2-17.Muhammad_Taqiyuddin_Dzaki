' VBScript to remove specific lines from a file
Option Explicit

Dim inputFile, outputFile, linesToRemove, fso, tsIn, tsOut, line, i, shouldRemove, num

inputFile = "c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index_fixed.php"
outputFile = "c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index_final.php"

' Lines to remove (1-based indexing)
linesToRemove = Array(1781, 1815)

Set fso = CreateObject("Scripting.FileSystemObject")

Set tsIn = fso.OpenTextFile(inputFile, 1) ' ForReading
Set tsOut = fso.CreateTextFile(outputFile, True) ' ForWriting

i = 1
Do While Not tsIn.AtEndOfStream
    line = tsIn.ReadLine
    
    ' Check if current line should be removed
    shouldRemove = False
    For Each num In linesToRemove
        If i = num Then
            shouldRemove = True
            Exit For
        End If
    Next
    
    ' Write line to output if not marked for removal
    If Not shouldRemove Then
        tsOut.WriteLine line
    End If
    
    i = i + 1
Loop

tsIn.Close
tsOut.Close

WScript.Echo "File processed successfully!"