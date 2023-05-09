import sys
import csv
import datetime

csvName=sys.argv[1]
srtName=sys.argv[2]

def getTimeStr(tstr):
    t=float(tstr.replace(",","."))
    total_sec=int(t/1000)
    ms=int(t-total_sec*1000)
    s=total_sec%60
    total_m=int(total_sec/60)
    m=total_m%60
    h=int(total_m/60)
    return "{:02d}:{:02d}:{:02d},{}".format(h,m,s,ms)

with open(csvName,"r") as csvFile:
    with open(srtName,"w") as srtFile:
        csvReader=csv.reader(csvFile,delimiter=';',quotechar='"')
        rowNum=0
        for row in csvReader:
            rowNum+=1
            if(rowNum==1):continue
            srtFile.write(''.join([row[0],'\n',getTimeStr(row[1]),' --> ',getTimeStr(row[2]),'\n',row[3],"\n\n"]))
